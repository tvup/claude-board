<?php

namespace Tests\Feature;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(string $sessionId = 'test-session', array $overrides = []): TelemetrySession
    {
        return TelemetrySession::create(array_merge([
            'session_id' => $sessionId,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $overrides));
    }

    public function test_dashboard_index_loads(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertViewIs('dashboard.index');
    }

    public function test_dashboard_data_returns_json(): void
    {
        $this->createSession();

        $response = $this->getJson('/api/dashboard-data');

        $response->assertOk();
        $response->assertJsonStructure([
            'summary' => [
                'total_sessions',
                'active_sessions',
                'total_cost',
                'total_tokens',
                'api_requests',
            ],
            'sessions',
            'tokenBreakdown',
            'locBreakdown',
            'costByModel',
            'toolUsage',
            'apiPerformance',
        ]);
    }

    public function test_dashboard_data_summary_counts(): void
    {
        $this->createSession('sess-1');
        $this->createSession('sess-2', ['last_seen_at' => now()->subHours(2)]);

        TelemetryMetric::create([
            'session_id' => 'sess-1',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 1.5,
            'recorded_at' => now(),
        ]);

        $response = $this->getJson('/api/dashboard-data');

        $response->assertOk();
        $data = $response->json();
        $this->assertSame(2, $data['summary']['total_sessions']);
        $this->assertSame(1, $data['summary']['active_sessions']);
        $this->assertSame(1.5, $data['summary']['total_cost']);
    }

    public function test_session_detail_page_loads(): void
    {
        $this->createSession('sess-detail');

        $response = $this->get('/sessions/sess-detail');

        $response->assertOk();
        $response->assertViewIs('dashboard.session');
    }

    public function test_session_detail_returns_404_for_missing(): void
    {
        $response = $this->get('/sessions/nonexistent');

        $response->assertStatus(404);
    }

    public function test_delete_session(): void
    {
        $this->createSession('sess-del');

        $response = $this->delete('/sessions/sess-del');

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'sess-del']);
    }

    public function test_merge_sessions(): void
    {
        $this->createSession('sess-source');
        $this->createSession('sess-target');

        TelemetryMetric::create([
            'session_id' => 'sess-source',
            'metric_name' => 'test',
            'metric_type' => 'sum',
            'value' => 1,
            'recorded_at' => now(),
        ]);

        $response = $this->post('/sessions/sess-source/merge', [
            'merge_into' => 'sess-target',
        ]);

        $response->assertRedirect(route('dashboard.session', 'sess-target'));
        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'sess-source']);
        $this->assertDatabaseHas('telemetry_metrics', ['session_id' => 'sess-target']);
    }

    public function test_merge_session_into_self_fails(): void
    {
        $this->createSession('sess-self');

        $response = $this->post('/sessions/sess-self/merge', [
            'merge_into' => 'sess-self',
        ]);

        $response->assertRedirect(route('dashboard.session', 'sess-self'));
        $response->assertSessionHas('error');
    }

    public function test_reset_all_clears_data(): void
    {
        $this->createSession('sess-reset');
        TelemetryMetric::create([
            'session_id' => 'sess-reset',
            'metric_name' => 'test',
            'metric_type' => 'sum',
            'value' => 1,
            'recorded_at' => now(),
        ]);

        $response = $this->delete('/reset');

        $response->assertRedirect(route('dashboard'));
        $this->assertSame(0, TelemetrySession::count());
        $this->assertSame(0, TelemetryMetric::count());
    }

    public function test_session_activity_endpoint(): void
    {
        $this->createSession('sess-activity', ['last_seen_at' => now()]);

        TelemetryEvent::create([
            'session_id' => 'sess-activity',
            'event_name' => 'tool_result',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true'],
            'recorded_at' => now(),
        ]);

        $response = $this->getJson('/api/sessions/sess-activity/activity');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'last_activity_at',
            'inactivity_seconds',
            'events_last_5min',
            'activity_rate',
            'current_activity',
            'progress_buckets',
            'recent',
        ]);
    }

    public function test_ungroup_session(): void
    {
        $this->createSession('sess-a', ['session_group_id' => 'group-1']);
        $this->createSession('sess-b', ['session_group_id' => 'group-1']);
        $this->createSession('sess-c', ['session_group_id' => 'group-1']);

        $response = $this->post('/sessions/sess-a/ungroup');

        $response->assertRedirect(route('dashboard.session', 'sess-a'));
        $response->assertSessionHas('success');

        $this->assertNull(TelemetrySession::where('session_id', 'sess-a')->first()->session_group_id);
        $this->assertSame('group-1', TelemetrySession::where('session_id', 'sess-b')->first()->session_group_id);
    }

    public function test_group_sessions(): void
    {
        $this->createSession('sess-x');
        $this->createSession('sess-y');

        $response = $this->post('/sessions/sess-x/group', [
            'group_with' => 'sess-y',
        ]);

        $response->assertRedirect(route('dashboard.session', 'sess-x'));

        $x = TelemetrySession::where('session_id', 'sess-x')->first();
        $y = TelemetrySession::where('session_id', 'sess-y')->first();
        $this->assertNotNull($x->session_group_id);
        $this->assertSame($x->session_group_id, $y->session_group_id);
    }

    public function test_group_session_with_self_fails(): void
    {
        $this->createSession('sess-self');

        $response = $this->post('/sessions/sess-self/group', [
            'group_with' => 'sess-self',
        ]);

        $response->assertRedirect(route('dashboard.session', 'sess-self'));
        $response->assertSessionHas('error');
    }

    public function test_session_detail_shows_grouped_sessions(): void
    {
        $this->createSession('sess-main', ['session_group_id' => 'group-1', 'project_name' => 'test-proj']);
        $this->createSession('sess-related', ['session_group_id' => 'group-1', 'project_name' => 'test-proj']);

        $response = $this->get('/sessions/sess-main');

        $response->assertOk();
        $response->assertSee('sess-related');
    }

    public function test_merge_nonexistent_source_returns_error(): void
    {
        $this->createSession('sess-target');

        $response = $this->post('/sessions/nonexistent/merge', [
            'merge_into' => 'sess-target',
        ]);

        $response->assertRedirect(route('dashboard.session', 'nonexistent'));
        $response->assertSessionHas('error');
    }

    public function test_ungroup_nonexistent_session_returns_error(): void
    {
        $response = $this->post('/sessions/nonexistent/ungroup');

        $response->assertRedirect(route('dashboard.session', 'nonexistent'));
        $response->assertSessionHas('error');
    }

    public function test_group_nonexistent_session_returns_error(): void
    {
        $this->createSession('sess-exists');

        $response = $this->post('/sessions/sess-exists/group', [
            'group_with' => 'nonexistent',
        ]);

        $response->assertRedirect(route('dashboard.session', 'sess-exists'));
        $response->assertSessionHas('error');
    }
}
