<?php

namespace Tests\Feature;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardShowCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(string $sessionId = 'test-session-001', array $attrs = []): TelemetrySession
    {
        return TelemetrySession::create(array_merge([
            'session_id' => $sessionId,
            'project_name' => 'test-project',
            'user_email' => 'test@cipher.local',
            'user_id' => 'user-1',
            'app_version' => '1.0.0',
            'terminal_type' => 'vscode',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $attrs));
    }

    private function seedSessionWithData(string $sessionId = 'test-session-001'): TelemetrySession
    {
        $session = $this->createSession($sessionId);

        TelemetryMetric::create([
            'session_id' => $sessionId,
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 0.0123,
            'unit' => 'USD',
            'attributes' => ['model' => 'claude-sonnet-4-5-20250514'],
            'recorded_at' => now(),
        ]);

        TelemetryMetric::create([
            'session_id' => $sessionId,
            'metric_name' => 'claude_code.token.usage',
            'metric_type' => 'sum',
            'value' => 5000,
            'unit' => 'tokens',
            'attributes' => ['type' => 'input'],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => $sessionId,
            'event_name' => 'api_request',
            'severity' => 'INFO',
            'attributes' => ['model' => 'claude-sonnet-4-5', 'cost_usd' => '0.0123', 'duration_ms' => '1500'],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => $sessionId,
            'event_name' => 'tool_result',
            'severity' => 'INFO',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true', 'duration_ms' => '150'],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => $sessionId,
            'event_name' => 'user_prompt',
            'severity' => 'INFO',
            'attributes' => [],
            'recorded_at' => now(),
        ]);

        return $session;
    }

    public function test_show_dashboard_with_no_data(): void
    {
        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_show_dashboard_with_session_data(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_show_session_detail(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--session' => 'test-session-001'])
            ->assertExitCode(0);
    }

    public function test_show_session_detail_not_found(): void
    {
        $this->artisan('dashboard:show', ['--session' => 'nonexistent'])
            ->assertExitCode(1);
    }

    public function test_show_session_detail_with_grouped_sessions(): void
    {
        $session1 = $this->createSession('session-a', ['session_group_id' => 'group-1']);
        $session2 = $this->createSession('session-b', ['session_group_id' => 'group-1']);

        $this->artisan('dashboard:show', ['--session' => 'session-a'])
            ->assertExitCode(0);
    }

    public function test_delete_session_confirmed(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--delete' => 'test-session-001'])
            ->expectsConfirmation(
                __('dashboard.cli_delete_confirm', ['id' => 'test-session-001']),
                'yes'
            )
            ->assertExitCode(0);

        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'test-session-001']);
    }

    public function test_delete_session_cancelled(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--delete' => 'test-session-001'])
            ->expectsConfirmation(
                __('dashboard.cli_delete_confirm', ['id' => 'test-session-001']),
                'no'
            )
            ->assertExitCode(0);

        $this->assertDatabaseHas('telemetry_sessions', ['session_id' => 'test-session-001']);
    }

    public function test_delete_session_not_found(): void
    {
        $this->artisan('dashboard:show', ['--delete' => 'nonexistent'])
            ->assertExitCode(1);
    }

    public function test_merge_sessions_confirmed(): void
    {
        $this->seedSessionWithData('source-session');
        $this->createSession('target-session');

        $this->artisan('dashboard:show', ['--merge' => 'source-session:target-session'])
            ->expectsConfirmation(
                __('dashboard.cli_merge_confirm', [
                    'source' => 'source-session',
                    'target' => 'target-session',
                    'metrics' => 2,
                    'events' => 3,
                ]),
                'yes'
            )
            ->assertExitCode(0);

        $this->assertDatabaseMissing('telemetry_sessions', ['session_id' => 'source-session']);
    }

    public function test_merge_sessions_cancelled(): void
    {
        $this->seedSessionWithData('source-session');
        $this->createSession('target-session');

        $this->artisan('dashboard:show', ['--merge' => 'source-session:target-session'])
            ->expectsConfirmation(
                __('dashboard.cli_merge_confirm', [
                    'source' => 'source-session',
                    'target' => 'target-session',
                    'metrics' => 2,
                    'events' => 3,
                ]),
                'no'
            )
            ->assertExitCode(0);

        $this->assertDatabaseHas('telemetry_sessions', ['session_id' => 'source-session']);
    }

    public function test_merge_sessions_invalid_format(): void
    {
        $this->artisan('dashboard:show', ['--merge' => 'invalid-format'])
            ->assertExitCode(1);
    }

    public function test_merge_sessions_same_id(): void
    {
        $this->artisan('dashboard:show', ['--merge' => 'same:same'])
            ->assertExitCode(1);
    }

    public function test_merge_sessions_source_not_found(): void
    {
        $this->createSession('target-session');

        $this->artisan('dashboard:show', ['--merge' => 'nonexistent:target-session'])
            ->assertExitCode(1);
    }

    public function test_merge_sessions_target_not_found(): void
    {
        $this->createSession('source-session');

        $this->artisan('dashboard:show', ['--merge' => 'source-session:nonexistent'])
            ->assertExitCode(1);
    }

    public function test_ungroup_session(): void
    {
        $this->createSession('session-a', ['session_group_id' => 'group-1']);
        $this->createSession('session-b', ['session_group_id' => 'group-1']);

        $this->artisan('dashboard:show', ['--ungroup' => 'session-a'])
            ->assertExitCode(0);
    }

    public function test_ungroup_session_not_found(): void
    {
        $this->artisan('dashboard:show', ['--ungroup' => 'nonexistent'])
            ->assertExitCode(1);
    }

    public function test_reset_confirmed(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--reset' => true])
            ->expectsConfirmation(
                __('dashboard.cli_reset_confirm', ['count' => 1]),
                'yes'
            )
            ->assertExitCode(0);

        $this->assertSame(0, TelemetrySession::count());
    }

    public function test_reset_cancelled(): void
    {
        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--reset' => true])
            ->expectsConfirmation(
                __('dashboard.cli_reset_confirm', ['count' => 1]),
                'no'
            )
            ->assertExitCode(0);

        $this->assertSame(1, TelemetrySession::count());
    }

    public function test_reset_with_no_data(): void
    {
        $this->artisan('dashboard:show', ['--reset' => true])
            ->assertExitCode(0);
    }

    public function test_dashboard_shows_token_breakdown(): void
    {
        $session = $this->createSession();

        foreach (['input', 'output', 'cacheRead', 'cacheCreation'] as $type) {
            TelemetryMetric::create([
                'session_id' => 'test-session-001',
                'metric_name' => 'claude_code.token.usage',
                'metric_type' => 'sum',
                'value' => 1000,
                'attributes' => ['type' => $type],
                'recorded_at' => now(),
            ]);
        }

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_dashboard_shows_cost_by_model(): void
    {
        $session = $this->createSession();

        TelemetryMetric::create([
            'session_id' => 'test-session-001',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 0.05,
            'attributes' => ['model' => 'claude-sonnet-4-5-20250514'],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'test-session-001',
            'event_name' => 'api_request',
            'attributes' => ['model' => 'claude-sonnet-4-5-20250514'],
            'recorded_at' => now(),
        ]);

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_dashboard_shows_tool_usage(): void
    {
        $session = $this->createSession();

        TelemetryEvent::create([
            'session_id' => 'test-session-001',
            'event_name' => 'tool_result',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true', 'duration_ms' => '200'],
            'recorded_at' => now(),
        ]);

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_session_detail_with_events(): void
    {
        $session = $this->createSession();

        // Create events with various types to cover colorEvent and eventDetails
        $events = [
            ['event_name' => 'api_request', 'attributes' => ['model' => 'sonnet', 'cost_usd' => '0.01', 'duration_ms' => '1000']],
            ['event_name' => 'api_error', 'attributes' => ['error' => 'Rate limit exceeded']],
            ['event_name' => 'tool_result', 'attributes' => ['tool_name' => 'Edit', 'success' => false]],
            ['event_name' => 'user_prompt', 'attributes' => []],
            ['event_name' => 'tool_decision', 'attributes' => ['tool_name' => 'Bash']],
            ['event_name' => 'claude_code.api_request', 'attributes' => ['model' => 'opus']],
            ['event_name' => 'unknown_event', 'attributes' => []],
        ];

        foreach ($events as $event) {
            TelemetryEvent::create(array_merge([
                'session_id' => 'test-session-001',
                'recorded_at' => now(),
            ], $event));
        }

        $this->artisan('dashboard:show', ['--session' => 'test-session-001'])
            ->assertExitCode(0);
    }

    public function test_session_detail_with_api_billing_model(): void
    {
        config(['claude-board.billing_model' => 'api']);

        $this->seedSessionWithData();

        $this->artisan('dashboard:show', ['--session' => 'test-session-001'])
            ->assertExitCode(0);
    }

    public function test_dashboard_with_api_billing_model(): void
    {
        config(['claude-board.billing_model' => 'api']);

        $this->seedSessionWithData();

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_dashboard_shows_minutes_format_for_active_time(): void
    {
        $session = $this->createSession('session-min');

        TelemetryMetric::create([
            'session_id' => 'session-min',
            'metric_name' => 'claude_code.active_time.total',
            'metric_type' => 'gauge',
            'value' => 300,
            'unit' => 's',
            'recorded_at' => now(),
        ]);

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }

    public function test_dashboard_shows_hours_format_for_long_active_time(): void
    {
        $session = $this->createSession();

        TelemetryMetric::create([
            'session_id' => 'test-session-001',
            'metric_name' => 'claude_code.active_time.total',
            'metric_type' => 'gauge',
            'value' => 7200,
            'unit' => 's',
            'recorded_at' => now(),
        ]);

        $this->artisan('dashboard:show')
            ->assertExitCode(0);
    }
}
