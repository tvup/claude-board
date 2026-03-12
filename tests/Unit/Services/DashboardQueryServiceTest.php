<?php

namespace Tests\Unit\Services;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use App\Services\DashboardQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    private DashboardQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DashboardQueryService();
    }

    private function createSession(string $sessionId = 'test-session', array $overrides = []): TelemetrySession
    {
        return TelemetrySession::create(array_merge([
            'session_id' => $sessionId,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ], $overrides));
    }

    public function test_get_summary_with_no_data(): void
    {
        $summary = $this->service->getSummary();

        $this->assertSame(0, $summary['total_sessions']);
        $this->assertSame(0, $summary['active_sessions']);
        $this->assertSame(0.0, $summary['total_cost']);
        $this->assertSame(0, $summary['total_tokens']);
        $this->assertSame(0, $summary['api_requests']);
    }

    public function test_get_summary_counts_sessions_and_metrics(): void
    {
        $this->createSession('sess-1');
        $this->createSession('sess-2', ['last_seen_at' => now()->subHours(2)]);

        TelemetryMetric::create([
            'session_id' => 'sess-1',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 2.5,
            'recorded_at' => now(),
        ]);

        TelemetryMetric::create([
            'session_id' => 'sess-1',
            'metric_name' => 'claude_code.token.usage',
            'metric_type' => 'sum',
            'value' => 1000,
            'recorded_at' => now(),
        ]);

        $summary = $this->service->getSummary();

        $this->assertSame(2, $summary['total_sessions']);
        $this->assertSame(1, $summary['active_sessions']);
        $this->assertSame(2.5, $summary['total_cost']);
        $this->assertSame(1000, $summary['total_tokens']);
    }

    public function test_get_summary_counts_api_events_with_both_prefixes(): void
    {
        $this->createSession('sess-1');

        TelemetryEvent::create([
            'session_id' => 'sess-1',
            'event_name' => 'api_request',
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-1',
            'event_name' => 'claude_code.api_request',
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-1',
            'event_name' => 'api_error',
            'recorded_at' => now(),
        ]);

        $summary = $this->service->getSummary();

        $this->assertSame(2, $summary['api_requests']);
        $this->assertSame(1, $summary['api_errors']);
    }

    public function test_get_sessions_returns_ordered_collection(): void
    {
        $this->createSession('sess-old', ['last_seen_at' => now()->subHours(2)]);
        $this->createSession('sess-new', ['last_seen_at' => now()]);

        $sessions = $this->service->getSessions();

        $this->assertCount(2, $sessions);
        $this->assertSame('sess-new', $sessions->first()->session_id);
    }

    public function test_get_session_detail(): void
    {
        $this->createSession('sess-detail');

        TelemetryMetric::create([
            'session_id' => 'sess-detail',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 0.75,
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-detail',
            'event_name' => 'tool_result',
            'recorded_at' => now(),
        ]);

        $detail = $this->service->getSessionDetail('sess-detail');

        $this->assertSame('sess-detail', $detail['session']->session_id);
        $this->assertCount(1, $detail['metrics']);
        $this->assertCount(1, $detail['events']);
        $this->assertSame(0.75, $detail['cost']);
    }

    public function test_get_session_detail_throws_for_missing(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->service->getSessionDetail('nonexistent');
    }

    public function test_get_tool_usage(): void
    {
        $this->createSession('sess-tools');

        TelemetryEvent::create([
            'session_id' => 'sess-tools',
            'event_name' => 'tool_result',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true', 'duration_ms' => 100],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-tools',
            'event_name' => 'tool_result',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true', 'duration_ms' => 200],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-tools',
            'event_name' => 'claude_code.tool_result',
            'attributes' => ['tool_name' => 'Write', 'success' => 'false', 'duration_ms' => 50],
            'recorded_at' => now(),
        ]);

        $usage = $this->service->getToolUsage();

        $readTool = $usage->firstWhere('tool_name', 'Read');
        $writeTool = $usage->firstWhere('tool_name', 'Write');

        $this->assertSame(2, (int) $readTool->invocations);
        $this->assertSame(2, (int) $readTool->successes);
        $this->assertSame(1, (int) $writeTool->invocations);
        $this->assertSame(0, (int) $writeTool->successes);
    }

    public function test_get_cost_by_model(): void
    {
        $this->createSession('sess-cost');

        TelemetryEvent::create([
            'session_id' => 'sess-cost',
            'event_name' => 'api_request',
            'attributes' => [
                'model' => 'claude-sonnet-4-20250514',
                'cost_usd' => 0.05,
                'input_tokens' => 500,
                'output_tokens' => 200,
            ],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-cost',
            'event_name' => 'api_request',
            'attributes' => [
                'model' => 'claude-sonnet-4-20250514',
                'cost_usd' => 0.03,
                'input_tokens' => 300,
                'output_tokens' => 100,
            ],
            'recorded_at' => now(),
        ]);

        $costByModel = $this->service->getCostByModel();

        $this->assertCount(1, $costByModel);
        $model = $costByModel->first();
        $this->assertSame('claude-sonnet-4-20250514', $model->model);
        $this->assertSame(2, (int) $model->request_count);
        $this->assertEquals(0.08, (float) $model->total_cost, '', 0.001);
    }

    public function test_get_token_breakdown(): void
    {
        $this->createSession('sess-tokens');

        TelemetryMetric::create([
            'session_id' => 'sess-tokens',
            'metric_name' => 'claude_code.token.usage',
            'metric_type' => 'sum',
            'value' => 500,
            'attributes' => ['type' => 'input'],
            'recorded_at' => now(),
        ]);

        TelemetryMetric::create([
            'session_id' => 'sess-tokens',
            'metric_name' => 'claude_code.token.usage',
            'metric_type' => 'sum',
            'value' => 200,
            'attributes' => ['type' => 'output'],
            'recorded_at' => now(),
        ]);

        $breakdown = $this->service->getTokenBreakdown();

        $this->assertSame(500, $breakdown['input']);
        $this->assertSame(200, $breakdown['output']);
        $this->assertSame(0, $breakdown['cache_read']);
    }

    public function test_get_lines_of_code_breakdown(): void
    {
        $this->createSession('sess-loc');

        TelemetryMetric::create([
            'session_id' => 'sess-loc',
            'metric_name' => 'claude_code.lines_of_code.count',
            'metric_type' => 'sum',
            'value' => 100,
            'attributes' => ['type' => 'added'],
            'recorded_at' => now(),
        ]);

        TelemetryMetric::create([
            'session_id' => 'sess-loc',
            'metric_name' => 'claude_code.lines_of_code.count',
            'metric_type' => 'sum',
            'value' => 30,
            'attributes' => ['type' => 'removed'],
            'recorded_at' => now(),
        ]);

        $breakdown = $this->service->getLinesOfCodeBreakdown();

        $this->assertSame(100, $breakdown['added']);
        $this->assertSame(30, $breakdown['removed']);
    }

    public function test_get_api_performance(): void
    {
        $this->createSession('sess-api');

        TelemetryEvent::create([
            'session_id' => 'sess-api',
            'event_name' => 'api_request',
            'attributes' => ['duration_ms' => 500],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-api',
            'event_name' => 'api_request',
            'attributes' => ['duration_ms' => 300],
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-api',
            'event_name' => 'api_error',
            'attributes' => ['error' => 'timeout'],
            'recorded_at' => now(),
        ]);

        $perf = $this->service->getApiPerformance();

        $this->assertSame(2, $perf['total_requests']);
        $this->assertSame(1, $perf['total_errors']);
        $this->assertSame(50.0, $perf['error_rate']);
        $this->assertSame(400, (int) $perf['avg_duration_ms']);
    }

    public function test_event_query_matches_both_prefixed_and_unprefixed(): void
    {
        $this->createSession('sess-dual');

        TelemetryEvent::create([
            'session_id' => 'sess-dual',
            'event_name' => 'api_request',
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'sess-dual',
            'event_name' => 'claude_code.api_request',
            'recorded_at' => now(),
        ]);

        $perf = $this->service->getApiPerformance();
        $this->assertSame(2, $perf['total_requests']);
    }
}
