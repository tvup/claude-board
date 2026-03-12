<?php

namespace Tests\Unit\Services;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use App\Services\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryServiceTest extends TestCase
{
    use RefreshDatabase;

    private TelemetryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TelemetryService();
    }

    private function createSession(array $overrides = []): TelemetrySession
    {
        return TelemetrySession::create(array_merge([
            'session_id' => 'sess-' . uniqid(),
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $overrides));
    }

    public function test_delete_session_removes_session(): void
    {
        $session = $this->createSession(['session_id' => 'sess-delete']);

        $result = $this->service->deleteSession('sess-delete');

        $this->assertTrue($result);
        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'sess-delete']);
    }

    public function test_delete_session_returns_false_for_missing(): void
    {
        $result = $this->service->deleteSession('nonexistent');

        $this->assertFalse($result);
    }

    public function test_merge_sessions_moves_metrics_and_events(): void
    {
        $source = $this->createSession(['session_id' => 'sess-src', 'first_seen_at' => now()->subHours(2)]);
        $target = $this->createSession(['session_id' => 'sess-tgt', 'first_seen_at' => now()->subHour()]);

        TelemetryMetric::create([
            'session_id' => 'sess-src',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 1.5,
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-src',
            'event_name' => 'tool_result',
            'recorded_at' => now(),
        ]);

        $this->service->mergeSessions('sess-src', 'sess-tgt');

        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'sess-src']);
        $this->assertDatabaseHas('telemetry_metrics', ['session_id' => 'sess-tgt', 'metric_name' => 'claude_code.cost.usage']);
        $this->assertDatabaseHas('telemetry_events', ['session_id' => 'sess-tgt', 'event_name' => 'tool_result']);

        $target->refresh();
        $this->assertTrue($target->first_seen_at <= $source->first_seen_at);
    }

    public function test_merge_sessions_fails_for_missing_source(): void
    {
        $this->createSession(['session_id' => 'sess-tgt']);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->mergeSessions('nonexistent', 'sess-tgt');
    }

    public function test_ungroup_session_removes_from_group(): void
    {
        $a = $this->createSession(['session_id' => 'sess-a', 'session_group_id' => 'group-1']);
        $b = $this->createSession(['session_id' => 'sess-b', 'session_group_id' => 'group-1']);
        $c = $this->createSession(['session_id' => 'sess-c', 'session_group_id' => 'group-1']);

        $this->service->ungroupSession('sess-a');

        $a->refresh();
        $this->assertNull($a->session_group_id);
        $this->assertSame('group-1', $b->fresh()->session_group_id);
        $this->assertSame('group-1', $c->fresh()->session_group_id);
    }

    public function test_ungroup_last_pair_clears_both(): void
    {
        $a = $this->createSession(['session_id' => 'sess-a', 'session_group_id' => 'group-1']);
        $b = $this->createSession(['session_id' => 'sess-b', 'session_group_id' => 'group-1']);

        $this->service->ungroupSession('sess-a');

        $this->assertNull($a->fresh()->session_group_id);
        $this->assertNull($b->fresh()->session_group_id);
    }

    public function test_group_sessions_assigns_same_group(): void
    {
        $a = $this->createSession(['session_id' => 'sess-a']);
        $b = $this->createSession(['session_id' => 'sess-b']);

        $this->service->groupSessions('sess-a', 'sess-b');

        $a->refresh();
        $b->refresh();
        $this->assertNotNull($a->session_group_id);
        $this->assertSame($a->session_group_id, $b->session_group_id);
    }

    public function test_group_sessions_merges_existing_groups(): void
    {
        $a = $this->createSession(['session_id' => 'sess-a', 'session_group_id' => 'group-a']);
        $b = $this->createSession(['session_id' => 'sess-b', 'session_group_id' => 'group-b']);
        $c = $this->createSession(['session_id' => 'sess-c', 'session_group_id' => 'group-b']);

        $this->service->groupSessions('sess-a', 'sess-b');

        $groupId = $a->fresh()->session_group_id;
        $this->assertSame($groupId, $b->fresh()->session_group_id);
        $this->assertSame($groupId, $c->fresh()->session_group_id);
    }

    public function test_reset_all_truncates_all_tables(): void
    {
        $this->createSession(['session_id' => 'sess-1']);
        TelemetryMetric::create([
            'session_id' => 'sess-1',
            'metric_name' => 'test',
            'metric_type' => 'sum',
            'value' => 1,
            'recorded_at' => now(),
        ]);
        TelemetryEvent::create([
            'session_id' => 'sess-1',
            'event_name' => 'test',
            'recorded_at' => now(),
        ]);

        $this->service->resetAll();

        $this->assertSame(0, TelemetrySession::count());
        $this->assertSame(0, TelemetryMetric::count());
        $this->assertSame(0, TelemetryEvent::count());
    }
}
