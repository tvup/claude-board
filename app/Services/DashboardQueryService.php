<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardQueryService
{
    /**
     * Match event names with or without 'claude_code.' prefix.
     */
    private function eventQuery(string $name): \Illuminate\Database\Eloquent\Builder
    {
        return TelemetryEvent::where(function ($q) use ($name) {
            $q->where('event_name', $name)
              ->orWhere('event_name', "claude_code.{$name}");
        });
    }

    public function getSummary(): array
    {
        $totalSessions = TelemetrySession::count();
        $activeSessions = TelemetrySession::where('last_seen_at', '>', now()->subMinutes(30))->count();

        $totalCost = TelemetryMetric::where('metric_name', 'claude_code.cost.usage')->sum('value');
        $totalTokens = TelemetryMetric::where('metric_name', 'claude_code.token.usage')->sum('value');
        $totalLoc = TelemetryMetric::where('metric_name', 'claude_code.lines_of_code.count')->sum('value');
        $totalCommits = TelemetryMetric::where('metric_name', 'claude_code.commit.count')->sum('value');
        $totalPrs = TelemetryMetric::where('metric_name', 'claude_code.pull_request.count')->sum('value');
        $totalActiveTime = TelemetryMetric::where('metric_name', 'claude_code.active_time.total')->sum('value');

        // API stats from events
        $apiRequests = $this->eventQuery('api_request')->count();
        $apiErrors = $this->eventQuery('api_error')->count();

        return [
            'total_sessions' => $totalSessions,
            'active_sessions' => $activeSessions,
            'total_cost' => round($totalCost, 4),
            'total_tokens' => (int) $totalTokens,
            'total_loc' => (int) $totalLoc,
            'total_commits' => (int) $totalCommits,
            'total_prs' => (int) $totalPrs,
            'total_active_time' => (int) $totalActiveTime,
            'api_requests' => $apiRequests,
            'api_errors' => $apiErrors,
        ];
    }

    public function getSessions(): Collection
    {
        $sessions = TelemetrySession::orderByDesc('last_seen_at')->get();

        if ($sessions->isEmpty()) {
            return $sessions;
        }

        $groupCounts = $sessions->whereNotNull('session_group_id')
            ->groupBy('session_group_id')
            ->map->count();

        $grouped = collect();
        $seen = [];

        foreach ($sessions as $session) {
            $gid = $session->session_group_id;

            if (! $gid || ($groupCounts[$gid] ?? 0) < 2) {
                $session->group_index = null;
                $session->group_size = null;
                $session->group_collapsed = false;
                $grouped->push($session);

                continue;
            }

            if (isset($seen[$gid])) {
                continue;
            }

            $seen[$gid] = true;
            $groupMembers = $sessions->where('session_group_id', $gid)
                ->sortByDesc('last_seen_at')
                ->values();

            foreach ($groupMembers as $i => $member) {
                $member->group_index = $groupMembers->count() - $i;
                $member->group_size = $groupMembers->count();
                $member->group_collapsed = $i > 0;
                $grouped->push($member);
            }
        }

        return $grouped;
    }

    public function getSessionDetail(string $sessionId): array
    {
        $session = TelemetrySession::where('session_id', $sessionId)->firstOrFail();
        $metrics = TelemetryMetric::where('session_id', $sessionId)->orderByDesc('recorded_at')->get();
        $events = TelemetryEvent::where('session_id', $sessionId)->orderByDesc('recorded_at')->get();

        $sessionCost = $metrics->where('metric_name', 'claude_code.cost.usage')->sum('value');
        $sessionTokens = $metrics->where('metric_name', 'claude_code.token.usage')->sum('value');

        return [
            'session' => $session,
            'metrics' => $metrics,
            'events' => $events,
            'cost' => round($sessionCost, 4),
            'tokens' => (int) $sessionTokens,
        ];
    }

    public function getSessionActivity(string $sessionId): array
    {
        $session = TelemetrySession::where('session_id', $sessionId)->firstOrFail();

        $lastSeenAt = $session->last_seen_at;
        $inactivitySeconds = $lastSeenAt ? (int) abs(now()->diffInSeconds($lastSeenAt)) : null;

        $status = match (true) {
            $inactivitySeconds === null => 'inactive',
            $inactivitySeconds < 60 => 'working',
            $inactivitySeconds < 1800 => 'idle',
            default => 'inactive',
        };

        $recentEvents = TelemetryEvent::where('session_id', $sessionId)
            ->orderByDesc('recorded_at')
            ->limit(15)
            ->get();

        $fiveMinAgo = now()->subMinutes(5);
        $eventsLast5Min = TelemetryEvent::where('session_id', $sessionId)
            ->where('recorded_at', '>=', $fiveMinAgo)
            ->count();

        $activityRate = round($eventsLast5Min / 5, 1);

        $recent = $recentEvents->map(function ($event) {
            $attrs = $event->attributes ?? [];
            $baseName = str_replace('claude_code.', '', $event->event_name);
            $detail = $attrs['tool_name'] ?? $attrs['model'] ?? null;

            return [
                'type' => 'event',
                'name' => $baseName,
                'detail' => $detail,
                'time' => $event->recorded_at?->format('H:i:s'),
            ];
        });

        // Current activity description from latest event
        $currentActivity = null;
        if ($recentEvents->isNotEmpty()) {
            $latest = $recentEvents->first();
            $latestAttrs = $latest->attributes ?? [];
            $latestBase = str_replace('claude_code.', '', $latest->event_name);
            $currentActivity = match ($latestBase) {
                'tool_result' => 'Used ' . ($latestAttrs['tool_name'] ?? 'tool') .
                    (isset($latestAttrs['success']) && ($latestAttrs['success'] === 'true' || $latestAttrs['success'] === true) ? '' : ' (failed)'),
                'tool_decision' => 'Deciding to use ' . ($latestAttrs['tool_name'] ?? 'tool'),
                'api_request' => 'API call to ' . ($latestAttrs['model'] ?? 'model') .
                    (isset($latestAttrs['duration_ms']) ? ' (' . $latestAttrs['duration_ms'] . 'ms)' : ''),
                'api_error' => 'API error' . (isset($latestAttrs['error']) ? ': ' . \Illuminate\Support\Str::limit($latestAttrs['error'], 80) : ''),
                'user_prompt' => 'User prompt received',
                default => $latestBase,
            };
        }

        // Progress: events per minute over 1-min windows for the last 5 minutes
        $progressBuckets = [];
        for ($i = 4; $i >= 0; $i--) {
            $from = now()->subMinutes($i + 1);
            $to = now()->subMinutes($i);
            $count = TelemetryEvent::where('session_id', $sessionId)
                ->where('recorded_at', '>=', $from)
                ->where('recorded_at', '<', $to)
                ->count();
            $progressBuckets[] = $count;
        }

        return [
            'status' => $status,
            'last_activity_at' => $lastSeenAt?->toIso8601String(),
            'inactivity_seconds' => $inactivitySeconds,
            'events_last_5min' => $eventsLast5Min,
            'activity_rate' => $activityRate,
            'current_activity' => $currentActivity,
            'progress_buckets' => $progressBuckets,
            'recent' => $recent->toArray(),
        ];
    }

    public function getRecentEvents(int $limit = 50): Collection
    {
        return TelemetryEvent::orderByDesc('recorded_at')
            ->limit($limit)
            ->get();
    }

    public function getCostByModel(): Collection
    {
        return TelemetryEvent::where(function ($q) {
                $q->where('event_name', 'api_request')
                  ->orWhere('event_name', 'claude_code.api_request');
            })
            ->select(
                DB::raw("json_extract(attributes, '$.model') as model"),
                DB::raw("SUM(json_extract(attributes, '$.cost_usd')) as total_cost"),
                DB::raw("COUNT(*) as request_count"),
                DB::raw("SUM(json_extract(attributes, '$.input_tokens')) as input_tokens"),
                DB::raw("SUM(json_extract(attributes, '$.output_tokens')) as output_tokens"),
                DB::raw("SUM(json_extract(attributes, '$.cache_read_tokens')) as cache_read_tokens"),
                DB::raw("SUM(json_extract(attributes, '$.cache_creation_tokens')) as cache_creation_tokens")
            )
            ->groupBy('model')
            ->orderByDesc('total_cost')
            ->get();
    }

    public function getToolUsage(): Collection
    {
        return TelemetryEvent::where(function ($q) {
                $q->where('event_name', 'tool_result')
                  ->orWhere('event_name', 'claude_code.tool_result');
            })
            ->select(
                DB::raw("json_extract(attributes, '$.tool_name') as tool_name"),
                DB::raw("COUNT(*) as invocations"),
                DB::raw("SUM(CASE WHEN json_extract(attributes, '$.success') IN ('true', 1) THEN 1 ELSE 0 END) as successes"),
                DB::raw("AVG(json_extract(attributes, '$.duration_ms')) as avg_duration_ms")
            )
            ->groupBy('tool_name')
            ->orderByDesc('invocations')
            ->get();
    }

    public function getApiPerformance(): array
    {
        $totalRequests = $this->eventQuery('api_request')->count();
        $totalErrors = $this->eventQuery('api_error')->count();
        $avgDuration = $this->eventQuery('api_request')
            ->avg(DB::raw("json_extract(attributes, '$.duration_ms')"));

        return [
            'total_requests' => $totalRequests,
            'total_errors' => $totalErrors,
            'error_rate' => $totalRequests > 0 ? round(($totalErrors / $totalRequests) * 100, 1) : 0,
            'avg_duration_ms' => round((float) $avgDuration, 0),
        ];
    }

    public function getTokenBreakdown(): array
    {
        $breakdown = TelemetryMetric::where('metric_name', 'claude_code.token.usage')
            ->select(
                DB::raw("json_extract(attributes, '$.type') as token_type"),
                DB::raw("SUM(value) as total")
            )
            ->groupBy('token_type')
            ->pluck('total', 'token_type')
            ->toArray();

        return [
            'input' => (int) ($breakdown['input'] ?? $breakdown['"input"'] ?? 0),
            'output' => (int) ($breakdown['output'] ?? $breakdown['"output"'] ?? 0),
            'cache_read' => (int) ($breakdown['cacheRead'] ?? $breakdown['"cacheRead"'] ?? 0),
            'cache_creation' => (int) ($breakdown['cacheCreation'] ?? $breakdown['"cacheCreation"'] ?? 0),
        ];
    }

    public function getLinesOfCodeBreakdown(): array
    {
        $breakdown = TelemetryMetric::where('metric_name', 'claude_code.lines_of_code.count')
            ->select(
                DB::raw("json_extract(attributes, '$.type') as loc_type"),
                DB::raw("SUM(value) as total")
            )
            ->groupBy('loc_type')
            ->pluck('total', 'loc_type')
            ->toArray();

        return [
            'added' => (int) ($breakdown['added'] ?? $breakdown['"added"'] ?? 0),
            'removed' => (int) ($breakdown['removed'] ?? $breakdown['"removed"'] ?? 0),
        ];
    }
}
