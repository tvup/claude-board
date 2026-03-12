<?php

namespace App\Console\Commands;

use App\Models\TelemetryMetric;
use App\Models\TelemetryEvent;
use App\Models\TelemetrySession;
use App\Services\DashboardQueryService;
use App\Services\TelemetryService;
use Illuminate\Console\Command;

class DashboardShow extends Command
{
    protected $signature = 'dashboard:show
        {--session= : Show details for a specific session ID}
        {--watch : Continuously refresh the dashboard}
        {--delete= : Delete a specific session and its data}
        {--reset : Reset all telemetry data}
        {--merge= : Merge two sessions (format: SOURCE_ID:TARGET_ID)}';

    protected $description = 'Display the Claude Board telemetry dashboard in the console';

    public function __construct(
        private readonly DashboardQueryService $query,
        private readonly TelemetryService $telemetry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('reset')) {
            return $this->resetAll();
        }

        if ($mergeArg = $this->option('merge')) {
            return $this->mergeSessions($mergeArg);
        }

        if ($deleteId = $this->option('delete')) {
            return $this->deleteSession($deleteId);
        }

        if ($this->option('watch')) {
            return $this->watchMode();
        }

        if ($sessionId = $this->option('session')) {
            return $this->showSession($sessionId);
        }

        return $this->showDashboard();
    }

    private function showDashboard(): int
    {
        $summary = $this->query->getSummary();
        $tokens = $this->query->getTokenBreakdown();
        $loc = $this->query->getLinesOfCodeBreakdown();
        $costByModel = $this->query->getCostByModel();
        $toolUsage = $this->query->getToolUsage();
        $apiPerf = $this->query->getApiPerformance();
        $events = $this->query->getRecentEvents(10);
        $isApi = config('claude-board.billing_model') === 'api';

        $this->newLine();
        $this->line('<fg=cyan;options=bold>  ╔══════════════════════════════════════════╗</>');
        $this->line('<fg=cyan;options=bold>  ║         '.__('dashboard.cli_title').'       ║</>');
        $this->line('<fg=cyan;options=bold>  ╚══════════════════════════════════════════╝</>');
        $this->newLine();

        $this->table(
            [__('dashboard.metric'), __('dashboard.value')],
            [
                [__('dashboard.cli_sessions_total'), $summary['total_sessions']],
                [__('dashboard.cli_sessions_active'), '<fg=green>'.$summary['active_sessions'].'</>'],
                [__('dashboard.cost_field_'.($isApi ? 'api' : 'subscription')), '<fg=yellow>$'.number_format($summary['total_cost'], 4).'</>'],
                [__('dashboard.cli_total_tokens'), number_format($summary['total_tokens'])],
                [__('dashboard.cli_active_time'), $this->formatSeconds($summary['total_active_time'])],
                [__('dashboard.cli_lines_added'), '<fg=green>+'.number_format($loc['added']).'</>'],
                [__('dashboard.cli_lines_removed'), '<fg=red>-'.number_format($loc['removed']).'</>'],
                [__('dashboard.commits'), $summary['total_commits']],
                [__('dashboard.cli_pull_requests'), $summary['total_prs']],
            ]
        );

        if (array_sum($tokens) > 0) {
            $this->newLine();
            $this->info('  '.__('dashboard.cli_token_breakdown'));
            $this->table(
                [__('dashboard.cli_type'), __('dashboard.cli_count')],
                [
                    [__('dashboard.input'), number_format($tokens['input'])],
                    [__('dashboard.output'), number_format($tokens['output'])],
                    [__('dashboard.cache_read'), number_format($tokens['cache_read'])],
                    [__('dashboard.cache_creation'), number_format($tokens['cache_creation'])],
                ]
            );
        }

        if ($costByModel->isNotEmpty()) {
            $this->newLine();
            $this->info('  '.__('dashboard.cli_cost_table_'.($isApi ? 'api' : 'subscription')));
            $this->table(
                [__('dashboard.model'), __('dashboard.cli_cost_col_'.($isApi ? 'api' : 'subscription')), __('dashboard.reqs')],
                $costByModel->map(fn ($row) => [
                    $row->model ?? 'unknown',
                    '$'.number_format((float) $row->total_cost, 4),
                    number_format((int) $row->request_count),
                ])->toArray()
            );
        }

        if ($toolUsage->isNotEmpty()) {
            $this->newLine();
            $this->info('  '.__('dashboard.cli_tool_usage'));
            $this->table(
                [__('dashboard.tool'), __('dashboard.cli_invocations'), __('dashboard.cli_success_rate'), __('dashboard.cli_avg_duration')],
                $toolUsage->map(fn ($row) => [
                    $row->tool_name ?? 'unknown',
                    number_format((int) $row->invocations),
                    $row->invocations > 0
                        ? round(($row->successes / $row->invocations) * 100, 1).'%'
                        : 'N/A',
                    round((float) $row->avg_duration_ms).'ms',
                ])->toArray()
            );
        }

        $this->newLine();
        $this->info('  '.__('dashboard.cli_api_performance'));
        $this->table(
            [__('dashboard.metric'), __('dashboard.value')],
            [
                [__('dashboard.total_requests'), number_format($apiPerf['total_requests'])],
                [__('dashboard.cli_total_errors'), $apiPerf['total_errors']],
                [__('dashboard.error_rate'), $apiPerf['error_rate'].'%'],
                [__('dashboard.cli_avg_response_time'), $apiPerf['avg_duration_ms'].'ms'],
            ]
        );

        if ($events->isNotEmpty()) {
            $this->newLine();
            $this->info('  '.__('dashboard.cli_recent_events'));
            $this->table(
                [__('dashboard.time'), __('dashboard.event'), __('dashboard.session'), __('dashboard.details')],
                $events->map(fn ($e) => [
                    $e->recorded_at?->format('H:i:s') ?? '-',
                    $this->colorEvent($e->event_name),
                    substr($e->session_id, 0, 12).'...',
                    $this->eventDetails($e),
                ])->toArray()
            );
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function showSession(string $sessionId): int
    {
        try {
            $data = $this->query->getSessionDetail($sessionId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            $this->error(__('dashboard.cli_session_not_found', ['id' => $sessionId]));

            return self::FAILURE;
        }

        $session = $data['session'];
        $isApi = ($session->billing_model ?? config('claude-board.billing_model')) === 'api';

        $this->newLine();
        $this->line('<fg=cyan;options=bold>  SESSION: '.$sessionId.'</>');
        $this->newLine();

        $this->table(
            [__('dashboard.cli_field'), __('dashboard.value')],
            [
                [__('dashboard.project'), $session->project_name ?? '-'],
                [__('dashboard.email'), $session->user_email ?? '-'],
                [__('dashboard.user_id'), $session->user_id ?? '-'],
                [__('dashboard.version'), $session->app_version ?? '-'],
                [__('dashboard.terminal'), $session->terminal_type ?? '-'],
                [__('dashboard.first_seen'), $session->first_seen_at?->format('Y-m-d H:i:s') ?? '-'],
                [__('dashboard.last_seen'), $session->last_seen_at?->format('Y-m-d H:i:s') ?? '-'],
                [__('dashboard.cost_field_'.($isApi ? 'api' : 'subscription')), '$'.number_format($data['cost'], 4)],
                [__('dashboard.tokens'), number_format($data['tokens'])],
            ]
        );

        if ($data['events']->isNotEmpty()) {
            $this->newLine();
            $this->info('  '.__('dashboard.events').' ('.$data['events']->count().')');
            $this->table(
                [__('dashboard.time'), __('dashboard.event'), __('dashboard.details')],
                $data['events']->take(25)->map(fn ($e) => [
                    $e->recorded_at?->format('H:i:s') ?? '-',
                    $this->colorEvent($e->event_name),
                    $this->eventDetails($e),
                ])->toArray()
            );
        }

        return self::SUCCESS;
    }

    private function deleteSession(string $sessionId): int
    {
        $session = TelemetrySession::where('session_id', $sessionId)->first();

        if (! $session) {
            $this->error(__('dashboard.cli_session_not_found', ['id' => $sessionId]));

            return self::FAILURE;
        }

        if (! $this->confirm(__('dashboard.cli_delete_confirm', ['id' => $sessionId]))) {
            $this->info(__('dashboard.cli_aborted'));

            return self::SUCCESS;
        }

        $this->telemetry->deleteSession($sessionId);
        $this->info(__('dashboard.cli_session_deleted', ['id' => $sessionId]));

        return self::SUCCESS;
    }

    private function mergeSessions(string $arg): int
    {
        $parts = explode(':', $arg);
        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            $this->error(__('dashboard.cli_merge_format'));

            return self::FAILURE;
        }

        [$sourceId, $targetId] = $parts;

        if ($sourceId === $targetId) {
            $this->error(__('dashboard.cli_merge_same'));

            return self::FAILURE;
        }

        $source = TelemetrySession::where('session_id', $sourceId)->first();
        $target = TelemetrySession::where('session_id', $targetId)->first();

        if (! $source) {
            $this->error(__('dashboard.cli_source_not_found', ['id' => $sourceId]));

            return self::FAILURE;
        }
        if (! $target) {
            $this->error(__('dashboard.cli_target_not_found', ['id' => $targetId]));

            return self::FAILURE;
        }

        $metricCount = TelemetryMetric::where('session_id', $sourceId)->count();
        $eventCount = TelemetryEvent::where('session_id', $sourceId)->count();

        if (! $this->confirm(__('dashboard.cli_merge_confirm', ['source' => $sourceId, 'target' => $targetId, 'metrics' => $metricCount, 'events' => $eventCount]))) {
            $this->info(__('dashboard.cli_aborted'));

            return self::SUCCESS;
        }

        $this->telemetry->mergeSessions($sourceId, $targetId);
        $this->info(__('dashboard.cli_session_merged', ['source' => $sourceId, 'target' => $targetId]));

        return self::SUCCESS;
    }

    private function resetAll(): int
    {
        $count = TelemetrySession::count();

        if ($count === 0) {
            $this->info(__('dashboard.cli_no_data'));

            return self::SUCCESS;
        }

        if (! $this->confirm(__('dashboard.cli_reset_confirm', ['count' => $count]))) {
            $this->info(__('dashboard.cli_aborted'));

            return self::SUCCESS;
        }

        $this->telemetry->resetAll();
        $this->info(__('dashboard.cli_reset_done'));

        return self::SUCCESS;
    }

    private function watchMode(): int
    {
        $this->info(__('dashboard.cli_watch_mode'));

        while (true) {
            system('clear');
            $this->showDashboard();
            sleep(5);
        }
    }

    private function colorEvent(string $eventName): string
    {
        $base = str_replace('claude_code.', '', $eventName);

        return match ($base) {
            'api_request' => '<fg=blue>'.$base.'</>',
            'api_error' => '<fg=red>'.$base.'</>',
            'tool_result' => '<fg=green>'.$base.'</>',
            'user_prompt' => '<fg=yellow>'.$base.'</>',
            'tool_decision' => '<fg=magenta>'.$base.'</>',
            default => $base,
        };
    }

    private function eventDetails(TelemetryEvent $event): string
    {
        $attrs = $event->attributes ?? [];
        $parts = [];

        if (isset($attrs['tool_name'])) {
            $parts[] = $attrs['tool_name'];
        }
        if (isset($attrs['model'])) {
            $parts[] = $attrs['model'];
        }
        if (isset($attrs['cost_usd'])) {
            $parts[] = '$'.$attrs['cost_usd'];
        }
        if (isset($attrs['duration_ms'])) {
            $parts[] = $attrs['duration_ms'].'ms';
        }
        if (isset($attrs['success'])) {
            $parts[] = $attrs['success'] === 'true' || $attrs['success'] === true ? 'OK' : 'FAIL';
        }
        if (isset($attrs['error'])) {
            $parts[] = substr($attrs['error'], 0, 40);
        }

        return implode(' | ', $parts) ?: '-';
    }

    private function formatSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            return round($seconds / 60, 1).'min';
        }

        return round($seconds / 3600, 1).'h';
    }
}
