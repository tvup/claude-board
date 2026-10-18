@php use App\Helpers\Format; @endphp
@extends('layouts.app')

@section('content')
@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-600/20 border border-green-600/40 rounded-lg text-green-400 text-sm">
    {{ session('success') }}
</div>
@endif

{{-- Summary Cards --}}
@php $isApi = ($billingModel ?? 'subscription') === 'api'; @endphp
<div id="summary-cards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('dashboard.sessions') }}</p>
        <p class="text-2xl font-bold text-white" data-field="total_sessions">{{ $summary['total_sessions'] }}</p>
        <p class="text-sm text-cyber-green mt-1"><span data-field="active_sessions">{{ $summary['active_sessions'] }}</span> {{ __('dashboard.active') }}</p>
    </div>
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1" title="{{ __('dashboard.cost_tooltip_' . ($isApi ? 'api' : 'subscription')) }}">
            {{ __('dashboard.cost_label_' . ($isApi ? 'api' : 'subscription')) }}
        </p>
        <p class="text-2xl font-bold text-cyber-amber" data-field="total_cost">{{ Format::currency($summary['total_cost']) }}</p>
        <p class="text-sm text-gray-400 mt-1">
            <span class="block">{{ __('dashboard.active_time') }}: <span data-field="total_active_time">{{ $summary['total_active_time'] > 3600 ? round($summary['total_active_time']/3600, 1).'h' : ($summary['total_active_time'] > 60 ? round($summary['total_active_time']/60, 1).'min' : $summary['total_active_time'].'s') }}</span></span>
            <span class="text-xs {{ $isApi ? 'text-yellow-500/60' : 'text-cyber-green/60' }}">{{ __('dashboard.cost_sub_' . ($isApi ? 'api' : 'subscription')) }}</span>
        </p>
    </div>
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('dashboard.tokens_metrics') }}</p>
        <p class="text-2xl font-bold text-cyber-blue" data-field="total_tokens">{{ Format::number($summary['total_tokens']) }}</p>
        <div class="flex gap-3 text-xs text-gray-400 mt-1">
            <span>{{ __('dashboard.in') }}: <span data-field="tokens_input">{{ Format::number($tokenBreakdown['input']) }}</span></span>
            <span>{{ __('dashboard.out') }}: <span data-field="tokens_output">{{ Format::number($tokenBreakdown['output']) }}</span></span>
        </div>
    </div>
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('dashboard.api_requests_events') }}</p>
        <p class="text-2xl font-bold text-white" data-field="api_requests">{{ Format::number($summary['api_requests']) }}</p>
        <a href="{{ route('dashboard.errors') }}" class="text-sm {{ $summary['api_errors'] > 0 ? 'text-red-400' : 'text-green-400' }} mt-1 block hover:underline"><span data-field="api_errors">{{ $summary['api_errors'] }}</span> {{ __('dashboard.errors') }}</a>
    </div>
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <p class="text-xs uppercase tracking-wider text-gray-500 mb-1">{{ __('dashboard.code_git') }}</p>
        <div class="flex items-baseline gap-2">
            <span class="text-green-400 text-lg font-semibold" data-field="loc_added">+{{ Format::number($locBreakdown['added']) }}</span>
            <span class="text-red-400 text-lg font-semibold" data-field="loc_removed">-{{ Format::number($locBreakdown['removed']) }}</span>
        </div>
        <div class="flex gap-3 text-xs text-gray-400 mt-1">
            <span>{{ __('dashboard.commits') }}: <span data-field="total_commits">{{ $summary['total_commits'] }}</span></span>
            <span>{{ __('dashboard.prs') }}: <span data-field="total_prs">{{ $summary['total_prs'] }}</span></span>
        </div>
    </div>
</div>

{{-- Claude Usage Status --}}
<div id="claude-usage-panel" class="{{ $claudeUsage ? '' : 'hidden' }}">
@if($claudeUsage)
@php
    $snapshot = $claudeUsage['snapshot_at'] ?? null;
    $plan = $claudeUsage['subscription_type'] ?? null;
    $usageColor = fn (float $pct, string $prefix): string => $prefix . ($pct > 80 ? 'red-400' : ($pct > 50 ? 'cyber-amber' : 'cyber-green'));
    $calcElapsed = function (?string $resetsAt, int $periodSeconds): array {
        if (! $resetsAt) {
            return [null, null];
        }
        $remaining = max(0, \Carbon\Carbon::parse($resetsAt)->getTimestamp() - now()->getTimestamp());
        $elapsed = $periodSeconds - $remaining;
        $pct = round(max(0, min(100, $elapsed / $periodSeconds * 100)), 1);
        $d = intdiv($remaining, 86400);
        $h = intdiv($remaining % 86400, 3600);
        $m = intdiv($remaining % 3600, 60);
        $label = $d > 0 ? "{$d}d {$h}t" : ($h > 0 ? "{$h}t {$m}m" : ($m > 0 ? "{$m}m" : '< 1m'));

        return [$pct, $label];
    };
    $paceMarker = function (float $usage, ?float $elapsed): array {
        if ($elapsed === null) {
            return [0.0, 'bg-gray-500', 0.0, 0.0];
        }
        $diff = $usage - $elapsed;
        $pos = $usage >= 100 ? 100.0 : round(min(100, max(0, 50 + $diff)), 1);
        $color = $usage >= 100 ? 'bg-red-400' : ($diff <= 0 ? 'bg-cyber-green' : ($diff <= 15 ? 'bg-cyber-amber' : 'bg-red-400'));

        return [$pos, $color, $diff <= 0 ? $pos : 50.0, round(abs($pos - 50), 1)];
    };
    $limits = [];
    foreach ([
        'five_hour' => [__('dashboard.current_session'), 5 * 3600],
        'seven_day' => [__('dashboard.this_week'), 7 * 86400],
    ] as $key => [$label, $periodSeconds]) {
        $pct = (float) ($claudeUsage[$key . '_utilization'] ?? 0);
        [$elapsed, $remaining] = $calcElapsed($claudeUsage[$key . '_resets_at'] ?? null, $periodSeconds);
        [$pacePos, $paceColor, $fillLeft, $fillWidth] = $paceMarker($pct, $elapsed);
        $limits[$key] = compact('label', 'pct', 'elapsed', 'remaining', 'pacePos', 'paceColor', 'fillLeft', 'fillWidth');
    }

    $cloudLeft = $claudeUsage['cloud_credits_left'] ?? null;
    $cloudTotal = $claudeUsage['cloud_credits_total'] ?? null;
    $cloudPct = $cloudLeft !== null && $cloudTotal ? round($cloudLeft / $cloudTotal * 100, 1) : 0;
    $cloudExpires = $claudeUsage['cloud_credits_expires_at'] ?? null;
    $balanceCents = $claudeUsage['balance_cents'] ?? 0;
    $creditsEnabled = $claudeUsage['usage_credits_enabled'] ?? null;
    $extraUsed = $claudeUsage['extra_usage_used_cents'] ?? 0;
    $extraLimit = $claudeUsage['extra_usage_limit_cents'] ?? null;
    $extraPct = $extraLimit ? round($extraUsed / $extraLimit * 100, 1) : 0;
    $limitResets = $claudeUsage['limit_resets_count'] ?? null;
@endphp
<div class="bg-panel border border-panel-border rounded-lg p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2">
            <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">{{ __('dashboard.usage_status') }}</h2>
            <span class="text-xs px-2 py-0.5 rounded border border-cyber-blue/40 text-cyber-blue {{ $plan ? '' : 'hidden' }}" data-field="usage_plan">{{ $plan }}</span>
        </div>
        @if($snapshot)
        <span class="text-xs text-gray-500" data-field="usage_snapshot">{{ __('dashboard.snapshot_at') }}: {{ \Carbon\Carbon::parse($snapshot)->locale(app()->getLocale())->diffForHumans() }}</span>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach($limits as $key => $limit)
        <div>
            <div class="flex items-baseline justify-between mb-1">
                <p class="text-xs text-gray-500">{{ $limit['label'] }}</p>
                <p class="text-lg font-bold {{ $usageColor($limit['pct'], 'text-') }}" data-field="usage_{{ $key }}">{{ $limit['pct'] }}%</p>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-1.5">
                <div class="h-1.5 rounded-full {{ $usageColor($limit['pct'], 'bg-') }}" style="width: {{ min($limit['pct'], 100) }}%" data-bar="usage_{{ $key }}"></div>
            </div>
            <div data-time-section="{{ $key }}" class="{{ $limit['elapsed'] !== null ? '' : 'hidden' }}">
                <p class="text-xs text-gray-500 mt-1" data-field="time_remaining_{{ $key }}">{{ $limit['remaining'] }} {{ __('dashboard.remaining') }}</p>
                <div class="w-full bg-gray-900 rounded-full h-1 mt-0.5">
                    <div class="h-1 rounded-full bg-gray-500" style="width: {{ $limit['elapsed'] ?? 0 }}%" data-bar="time_{{ $key }}"></div>
                </div>
                <div class="relative w-full bg-gray-800 rounded h-2 mt-2" data-pace-bar="{{ $key }}">
                    <div class="absolute inset-y-0 rounded {{ $limit['paceColor'] }} opacity-60" style="left:{{ $limit['fillLeft'] }}%;width:{{ $limit['fillWidth'] }}%" data-bar="pace_fill_{{ $key }}"></div>
                    <div class="absolute -top-1 -bottom-1 w-px bg-gray-400" style="left:50%"></div>
                    <div class="absolute inset-y-0 w-1 rounded-sm {{ $limit['paceColor'] }}" style="left:{{ $limit['pacePos'] }}%;transform:translateX(-50%)" data-bar="pace_marker_{{ $key }}"></div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-5 pt-4 border-t border-gray-800">
        {{-- Cloud session credits --}}
        <div data-usage-card="cloud_credits" class="{{ $cloudLeft !== null ? '' : 'hidden' }}">
            <p class="text-xs text-gray-500 mb-1">{{ __('dashboard.cloud_credits') }}</p>
            <p class="text-lg font-bold {{ $cloudPct < 20 ? 'text-red-400' : ($cloudPct < 50 ? 'text-cyber-amber' : 'text-cyber-green') }}" data-field="usage_cloud_credits">{{ Format::currency((float) $cloudLeft) }} {{ __('dashboard.of') }} {{ Format::currency((float) $cloudTotal) }}</p>
            <div class="w-full bg-gray-800 rounded-full h-1.5 mt-1">
                <div class="h-1.5 rounded-full {{ $cloudPct < 20 ? 'bg-red-400' : ($cloudPct < 50 ? 'bg-cyber-amber' : 'bg-cyber-green') }}" style="width: {{ min(100 - $cloudPct, 100) }}%" data-bar="usage_cloud_credits"></div>
            </div>
            <p class="text-xs text-gray-500 mt-1" data-field="usage_cloud_expires">@if($cloudExpires){{ __('dashboard.expires') }} {{ \Carbon\Carbon::parse($cloudExpires)->locale(app()->getLocale())->isoFormat('D. MMM HH:mm') }}@endif</p>
        </div>
        {{-- Usage credits --}}
        <div>
            <p class="text-xs text-gray-500 mb-1">{{ __('dashboard.usage_credits') }}</p>
            <p class="text-lg font-bold text-gray-200" data-field="usage_balance">{{ Format::currency($balanceCents / 100) }}</p>
            <span class="inline-block text-xs mt-1 px-1.5 rounded {{ $creditsEnabled ? 'bg-cyber-green/20 text-cyber-green' : 'bg-gray-800 text-gray-500' }} {{ $creditsEnabled === null ? 'hidden' : '' }}" data-field="usage_credits_enabled">{{ $creditsEnabled ? __('dashboard.enabled') : __('dashboard.disabled') }}</span>
        </div>
        {{-- Monthly spend limit --}}
        <div data-usage-card="monthly_spend" class="{{ $extraLimit ? '' : 'hidden' }}">
            <p class="text-xs text-gray-500 mb-1">{{ __('dashboard.monthly_spend_limit') }}</p>
            <p class="text-lg font-bold {{ $extraPct > 90 ? 'text-red-400' : ($extraPct > 70 ? 'text-cyber-amber' : 'text-cyber-blue') }}" data-field="usage_extra">{{ Format::currency($extraUsed / 100) }} {{ __('dashboard.of') }} {{ Format::currency(($extraLimit ?? 0) / 100) }}</p>
            <div class="w-full bg-gray-800 rounded-full h-1.5 mt-1">
                <div class="h-1.5 rounded-full {{ $extraPct > 90 ? 'bg-red-400' : ($extraPct > 70 ? 'bg-cyber-amber' : 'bg-cyber-blue') }}" style="width: {{ min($extraPct, 100) }}%" data-bar="usage_extra"></div>
            </div>
        </div>
        {{-- Limit resets --}}
        <div data-usage-card="limit_resets" class="{{ $limitResets !== null ? '' : 'hidden' }}">
            <p class="text-xs text-gray-500 mb-1">{{ __('dashboard.limit_resets') }}</p>
            <p class="text-lg font-bold {{ $limitResets ? 'text-cyber-green' : 'text-gray-500' }}" data-field="usage_limit_resets">{{ $limitResets ?: __('dashboard.no_limit_resets') }}</p>
        </div>
    </div>
</div>
@endif
</div>

{{-- Two-column: Cost by Model + Token Breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.cost_table_' . ($isApi ? 'api' : 'subscription')) }}</h2>
        <div id="cost-by-model">
            @if($costByModel->isEmpty())
                <p class="text-gray-500 text-sm">{{ __('dashboard.no_api_data') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead><tr class="text-gray-500 text-left"><th class="pb-2">{{ __('dashboard.model') }}</th><th class="pb-2 text-right">{{ __('dashboard.cost_col_' . ($isApi ? 'api' : 'subscription')) }}</th><th class="pb-2 text-right">{{ __('dashboard.reqs') }}</th><th class="pb-2 text-right">{{ __('dashboard.in') }}</th><th class="pb-2 text-right">{{ __('dashboard.out') }}</th><th class="pb-2 text-right">{{ __('dashboard.cache_r') }}</th><th class="pb-2 text-right">{{ __('dashboard.cache_w') }}</th></tr></thead>
                    <tbody>
                    @foreach($costByModel as $row)
                        <tr class="border-t border-gray-800">
                            <td class="py-2 text-gray-300">{{ $row->model ?? 'unknown' }}</td>
                            <td class="py-2 text-right text-cyber-amber">{{ Format::currency((float)$row->total_cost, 4) }}</td>
                            <td class="py-2 text-right text-gray-400">{{ Format::number((int)$row->request_count) }}</td>
                            <td class="py-2 text-right text-gray-400 text-xs">{{ Format::number((int)$row->input_tokens) }}</td>
                            <td class="py-2 text-right text-gray-400 text-xs">{{ Format::number((int)$row->output_tokens) }}</td>
                            <td class="py-2 text-right text-gray-400 text-xs">{{ Format::number((int)$row->cache_read_tokens) }}</td>
                            <td class="py-2 text-right text-gray-400 text-xs">{{ Format::number((int)$row->cache_creation_tokens) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.token_breakdown') }}</h2>
        <div id="token-breakdown" class="space-y-3">
            @php $maxTokens = max(1, max($tokenBreakdown['input'], $tokenBreakdown['output'], $tokenBreakdown['cache_read'], $tokenBreakdown['cache_creation'])); @endphp
            @foreach([
                [__('dashboard.input'), $tokenBreakdown['input'], 'bg-blue-500'],
                [__('dashboard.output'), $tokenBreakdown['output'], 'bg-purple-500'],
                [__('dashboard.cache_read'), $tokenBreakdown['cache_read'], 'bg-green-500'],
                [__('dashboard.cache_creation'), $tokenBreakdown['cache_creation'], 'bg-amber-500'],
            ] as [$label, $value, $color])
                <div>
                    <div class="flex justify-between text-sm mb-1">
                        <span class="text-gray-400">{{ $label }}</span>
                        <span class="text-gray-300">{{ Format::number($value) }}</span>
                    </div>
                    <div class="w-full bg-gray-800 rounded-full h-2">
                        <div class="{{ $color }} rounded-full h-2 transition-all" style="width: {{ $maxTokens > 0 ? round(($value / $maxTokens) * 100) : 0 }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Two-column: Tool Usage + API Performance --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.tool_usage') }}</h2>
        <div id="tool-usage" class="overflow-y-auto pr-2" style="max-height: 200px;">
            @if($toolUsage->isEmpty())
                <p class="text-gray-500 text-sm">{{ __('dashboard.no_tool_data') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead><tr class="text-gray-500 text-left"><th class="pb-2">{{ __('dashboard.tool') }}</th><th class="pb-2 text-right">{{ __('dashboard.calls') }}</th><th class="pb-2 text-right">{{ __('dashboard.success') }}</th><th class="pb-2 text-right">{{ __('dashboard.avg_ms') }}</th></tr></thead>
                    <tbody>
                    @foreach($toolUsage as $row)
                        <tr class="border-t border-gray-800">
                            <td class="py-2 text-gray-300 font-mono text-xs">{{ $row->tool_name ?? 'unknown' }}</td>
                            <td class="py-2 text-right text-gray-400">{{ Format::number((int)$row->invocations) }}</td>
                            <td class="py-2 text-right {{ ($row->invocations > 0 && ($row->successes / $row->invocations) >= 0.95) ? 'text-green-400' : 'text-yellow-400' }}">
                                {{ $row->invocations > 0 ? round(($row->successes / $row->invocations) * 100, 1) : 0 }}%
                            </td>
                            <td class="py-2 text-right text-gray-400">{{ round((float)$row->avg_duration_ms) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="bg-panel border border-panel-border rounded-lg p-5">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.api_performance') }}</h2>
        <div id="api-performance" class="grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-500">{{ __('dashboard.total_requests') }}</p>
                <p class="text-xl font-bold text-white" data-field="api_total_requests">{{ Format::number($apiPerformance['total_requests']) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('dashboard.avg_response') }}</p>
                <p class="text-xl font-bold text-cyber-blue" data-field="api_avg_duration">{{ $apiPerformance['avg_duration_ms'] }}ms</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('dashboard.errors') }}</p>
                <a href="{{ route('dashboard.errors') }}" class="text-xl font-bold {{ $apiPerformance['total_errors'] > 0 ? 'text-red-400' : 'text-green-400' }} hover:underline block" data-field="api_total_errors">{{ $apiPerformance['total_errors'] }}</a>
            </div>
            <div>
                <p class="text-xs text-gray-500">{{ __('dashboard.error_rate') }}</p>
                <p class="text-xl font-bold {{ $apiPerformance['error_rate'] > 5 ? 'text-red-400' : 'text-green-400' }}" data-field="api_error_rate">{{ $apiPerformance['error_rate'] }}%</p>
            </div>
        </div>
    </div>
</div>

{{-- Sessions --}}
<div class="bg-panel border border-panel-border rounded-lg p-5 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">{{ __('dashboard.sessions') }}</h2>
        @if($sessions->isNotEmpty())
        <form method="POST" action="{{ route('dashboard.reset') }}" onsubmit="return confirm('{{ __('dashboard.reset_confirm') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3 py-1 text-xs bg-red-600/20 text-red-400 border border-red-600/40 rounded hover:bg-red-600/40 transition">{{ __('dashboard.reset_all_data') }}</button>
        </form>
        @endif
    </div>
    <div id="sessions-table" class="overflow-x-auto">
        @if($sessions->isEmpty())
            <p class="text-gray-500 text-sm">{{ __('dashboard.no_sessions') }}</p>
        @else
            <table class="w-full text-sm">
                <thead><tr class="text-gray-500 text-left"><th class="pb-2 w-8"></th><th class="pb-2">{{ __('dashboard.session_id') }}</th><th class="pb-2">{{ __('dashboard.project') }}</th><th class="pb-2">{{ __('dashboard.email') }}</th><th class="pb-2">{{ __('dashboard.terminal') }}</th><th class="pb-2">{{ __('dashboard.hostname') }}</th><th class="pb-2">{{ __('dashboard.version') }}</th><th class="pb-2 text-right">{{ __('dashboard.last_seen') }}</th><th class="pb-2 text-right">{{ __('dashboard.actions') }}</th></tr></thead>
                <tbody>
                @php
                    $groupColors = ['border-cyan-500', 'border-purple-500', 'border-amber-500', 'border-emerald-500', 'border-rose-500', 'border-indigo-500', 'border-lime-500', 'border-fuchsia-500'];
                    $groupColorMap = [];
                    $colorIdx = 0;
                @endphp
                @foreach($sessions as $session)
                    @php
                        $inactSec = $session->last_seen_at ? abs(now()->diffInSeconds($session->last_seen_at)) : null;
                        $statusDot = match(true) {
                            $inactSec !== null && $inactSec < 60 => 'bg-cyber-green animate-pulse',
                            $inactSec !== null && $inactSec < 1800 => 'bg-yellow-500',
                            default => 'bg-gray-600',
                        };
                        $statusTitle = match(true) {
                            $inactSec !== null && $inactSec < 60 => __('dashboard.working'),
                            $inactSec !== null && $inactSec < 1800 => __('dashboard.idle') . ' ' . round($inactSec / 60) . 'm',
                            default => __('dashboard.inactive'),
                        };
                        $gid = $session->session_group_id;
                        $hasGroup = $session->group_size !== null && $session->group_size >= 2;
                        $isCollapsed = $session->group_collapsed ?? false;
                        $isGroupHead = $hasGroup && !$isCollapsed;
                        $groupBorder = '';
                        if ($hasGroup && $gid) {
                            if (!isset($groupColorMap[$gid])) {
                                $groupColorMap[$gid] = $groupColors[$colorIdx % count($groupColors)];
                                $colorIdx++;
                            }
                            $groupBorder = 'border-l-3 ' . $groupColorMap[$gid];
                        }
                    @endphp
                    <tr class="border-t border-gray-800 hover:bg-gray-900/50 {{ $groupBorder }}{{ $isCollapsed ? ' hidden' : '' }}" @if($hasGroup) data-group="{{ $gid }}" data-group-collapsed="{{ $isCollapsed ? '1' : '0' }}" @endif>
                        <td class="py-2 text-center"><span class="w-2.5 h-2.5 rounded-full {{ $statusDot }} inline-block" title="{{ $statusTitle }}"></span></td>
                        <td class="py-2">
                            @if($isGroupHead)
                                <button onclick="toggleGroup('{{ $gid }}')" class="mr-1 text-gray-500 hover:text-gray-300 text-xs font-mono transition" data-group-toggle="{{ $gid }}">▶ {{ $session->group_size }}</button>
                            @endif
                            <a href="{{ route('dashboard.session', $session->session_id) }}" class="text-cyber-blue hover:underline font-mono text-xs">{{ \Illuminate\Support\Str::limit($session->session_id, 24) }}</a>
                            @if($hasGroup && $session->group_index < $session->group_size)
                                <span class="ml-1 text-[10px] text-gray-500 font-mono" title="{{ $session->group_index }}/{{ $session->group_size }}">{{ $session->group_index }}/{{ $session->group_size }}</span>
                            @endif
                        </td>
                        <td class="py-2 text-cyber-green font-semibold text-sm">{{ $session->project_name ?? '-' }}</td>
                        <td class="py-2 text-gray-400">{{ $session->user_email ?? '-' }}</td>
                        <td class="py-2 text-gray-400">{{ $session->terminal_type ?? '-' }}</td>
                        <td class="py-2 text-gray-400 text-xs">{{ $session->hostname ?? '-' }}</td>
                        <td class="py-2 text-gray-400 font-mono text-xs">{{ $session->app_version ?? '-' }}</td>
                        <td class="py-2 text-right text-gray-400">{{ Format::relative($session->last_seen_at) }}</td>
                        <td class="py-2 text-right flex items-center justify-end gap-2">
                            <button type="button" onclick="startGrouping('{{ $session->session_id }}')" class="text-cyan-400/60 hover:text-cyan-400 transition text-xs group-btn" data-session-id="{{ $session->session_id }}">{{ __('dashboard.group') }}</button>
                            <form method="POST" action="{{ route('dashboard.session.destroy', $session->session_id) }}" onsubmit="return confirm('{{ __('dashboard.delete_session_confirm', ['id' => $session->session_id]) }}')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400/60 hover:text-red-400 transition text-xs">{{ __('dashboard.delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

{{-- Recent Events --}}
<div class="bg-panel border border-panel-border rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.recent_events') }}</h2>
    <div id="recent-events" class="overflow-x-auto max-h-96 overflow-y-auto">
        @if($recentEvents->isEmpty())
            <p class="text-gray-500 text-sm">{{ __('dashboard.no_events') }}</p>
        @else
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-950"><tr class="text-gray-500 text-left"><th class="pb-2">{{ __('dashboard.time') }}</th><th class="pb-2">{{ __('dashboard.event') }}</th><th class="pb-2">{{ __('dashboard.session') }}</th><th class="pb-2">{{ __('dashboard.details') }}</th></tr></thead>
                <tbody>
                @foreach($recentEvents as $event)
                    @php
                        $attrs = $event->attributes ?? [];
                        $details = collect([
                            isset($attrs['tool_name']) ? $attrs['tool_name'] : null,
                            isset($attrs['model']) ? $attrs['model'] : null,
                            isset($attrs['cost_usd']) ? Format::currency((float)$attrs['cost_usd'], 4) : null,
                            isset($attrs['duration_ms']) ? $attrs['duration_ms'].'ms' : null,
                            isset($attrs['success']) ? (($attrs['success'] === 'true' || $attrs['success'] === true) ? 'OK' : 'FAIL') : null,
                        ])->filter()->implode(' | ');
                        $baseName = str_replace('claude_code.', '', $event->event_name);
                        $eventColor = match($baseName) {
                            'api_request' => 'text-blue-400',
                            'api_error' => 'text-red-400',
                            'tool_result' => 'text-green-400',
                            'user_prompt' => 'text-yellow-400',
                            'tool_decision' => 'text-purple-400',
                            default => 'text-gray-400',
                        };
                    @endphp
                    <tr class="border-t border-gray-800">
                        <td class="py-1.5 text-gray-500 font-mono text-xs whitespace-nowrap">{{ Format::dateTime($event->recorded_at, 'time') }}</td>
                        <td class="py-1.5 {{ $eventColor }} font-mono text-xs">{{ str_replace('claude_code.', '', $event->event_name) }}</td>
                        <td class="py-1.5 text-gray-500 font-mono text-xs">{{ \Illuminate\Support\Str::limit($event->session_id, 12) }}</td>
                        <td class="py-1.5 text-gray-400 text-xs">{{ $details ?: '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection

@section('scripts')
<script>
    const expandedGroups = new Set();

    function toggleGroup(gid) {
        const rows = document.querySelectorAll('[data-group="' + gid + '"][data-group-collapsed="1"]');
        const toggle = document.querySelector('[data-group-toggle="' + gid + '"]');
        const isExpanded = expandedGroups.has(gid);
        if (isExpanded) {
            rows.forEach(r => r.classList.add('hidden'));
            expandedGroups.delete(gid);
            if (toggle) toggle.textContent = '▶ ' + (rows.length + 1);
        } else {
            rows.forEach(r => r.classList.remove('hidden'));
            expandedGroups.add(gid);
            if (toggle) toggle.textContent = '▼ ' + (rows.length + 1);
        }
    }

    let groupingSource = null;

    function startGrouping(sessionId) {
        if (groupingSource === sessionId) {
            cancelGrouping();
            return;
        }
        groupingSource = sessionId;
        document.querySelectorAll('.group-btn').forEach(btn => {
            const sid = btn.dataset.sessionId;
            if (sid === sessionId) {
                btn.textContent = TRANSLATIONS.cancel;
                btn.classList.remove('text-cyan-400/60', 'hover:text-cyan-400');
                btn.classList.add('text-yellow-400', 'hover:text-yellow-300');
            } else {
                btn.textContent = TRANSLATIONS.group_with;
                btn.classList.remove('text-cyan-400/60', 'hover:text-cyan-400');
                btn.classList.add('text-cyber-green', 'hover:text-green-300');
                btn.onclick = function() { submitGrouping(sessionId, sid); };
            }
        });
    }

    function cancelGrouping() {
        groupingSource = null;
        document.querySelectorAll('.group-btn').forEach(btn => {
            const sid = btn.dataset.sessionId;
            btn.textContent = TRANSLATIONS.group;
            btn.className = 'text-cyan-400/60 hover:text-cyan-400 transition text-xs group-btn';
            btn.onclick = function() { startGrouping(sid); };
        });
    }

    function submitGrouping(sourceId, targetId) {
        const csrfToken = document.querySelector('meta[name=csrf-token]');
        if (!csrfToken) return;
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/sessions/' + encodeURIComponent(sourceId) + '/group';
        form.innerHTML = '<input type="hidden" name="_token" value="' + csrfToken.content + '">' +
            '<input type="hidden" name="group_with" value="' + esc(targetId) + '">';
        document.body.appendChild(form);
        form.submit();
    }

    const REFRESH_INTERVAL = 5000;
    const LOCALE = @json(app()->getLocale());
    let billingModel = @json($billingModel ?? 'subscription');
    @php $jsTranslations = [
        'updated' => __('dashboard.updated'),
        'live' => __('dashboard.live'),
        'disconnected' => __('dashboard.disconnected'),
        'working' => __('dashboard.working'),
        'idle' => __('dashboard.idle'),
        'inactive' => __('dashboard.inactive'),
        'no_sessions' => __('dashboard.no_sessions'),
        'no_events' => __('dashboard.no_events'),
        'delete' => __('dashboard.delete'),
        'delete_confirm' => __('dashboard.delete_session_confirm', ['id' => '__ID__']),
        'seconds_ago' => __('dashboard.seconds_ago'),
        'minutes_ago' => __('dashboard.minutes_ago'),
        'hours_ago' => __('dashboard.hours_ago'),
        'days_ago' => __('dashboard.days_ago'),
        'session_id' => __('dashboard.session_id'),
        'project' => __('dashboard.project'),
        'email' => __('dashboard.email'),
        'terminal' => __('dashboard.terminal'),
        'hostname' => __('dashboard.hostname'),
        'version' => __('dashboard.version'),
        'last_seen' => __('dashboard.last_seen'),
        'actions' => __('dashboard.actions'),
        'group' => __('dashboard.group'),
        'group_with' => __('dashboard.group_with'),
        'cancel' => __('dashboard.cancel'),
        'time' => __('dashboard.time'),
        'event' => __('dashboard.event'),
        'session' => __('dashboard.session'),
        'details' => __('dashboard.details'),
        'snapshot_at' => __('dashboard.snapshot_at'),
        'of' => __('dashboard.of'),
        'remaining' => __('dashboard.remaining'),
        'expires' => __('dashboard.expires'),
        'enabled' => __('dashboard.enabled'),
        'disabled' => __('dashboard.disabled'),
        'no_limit_resets' => __('dashboard.no_limit_resets'),
    ]; @endphp
    const TRANSLATIONS = @json($jsTranslations);

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(str));
        return d.innerHTML;
    }

    function fmt(n) { return Number(n).toLocaleString(LOCALE); }
    function fmtUsd(n) {
        const val = Number(n).toLocaleString(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return '$' + val;
    }
    function fmtTime(s) {
        if (s < 60) return s + 's';
        if (s < 3600) return (s / 60).toFixed(1) + 'min';
        return (s / 3600).toFixed(1) + 'h';
    }

    function setField(name, val) {
        document.querySelectorAll('[data-field="' + name + '"]').forEach(el => el.textContent = val);
    }

    function relativeTime(dateStr) {
        if (!dateStr) return '-';
        const diff = Math.abs(Math.round((Date.now() - new Date(dateStr).getTime()) / 1000));
        if (diff < 60) return diff + ' ' + TRANSLATIONS.seconds_ago;
        if (diff < 3600) return Math.round(diff / 60) + ' ' + TRANSLATIONS.minutes_ago;
        if (diff < 86400) return Math.round(diff / 3600) + ' ' + TRANSLATIONS.hours_ago;
        return Math.round(diff / 86400) + ' ' + TRANSLATIONS.days_ago;
    }

    function updateClaudeUsage(usage) {
        const panel = document.getElementById('claude-usage-panel');
        if (!panel) return;
        if (!usage) { panel.classList.add('hidden'); return; }
        panel.classList.remove('hidden');

        const fiveH = usage.five_hour_utilization ?? 0;
        const sevenD = usage.seven_day_utilization ?? 0;
        const balanceCents = usage.balance_cents ?? 0;
        const extraUsed = usage.extra_usage_used_cents ?? 0;
        const extraLimit = usage.extra_usage_limit_cents ?? null;
        const extraPct = extraLimit ? Math.round(extraUsed / extraLimit * 1000) / 10 : 0;
        const cloudLeft = usage.cloud_credits_left ?? null;
        const cloudTotal = usage.cloud_credits_total ?? null;
        const cloudPct = cloudLeft !== null && cloudTotal ? Math.round(cloudLeft / cloudTotal * 1000) / 10 : 0;
        const limitResets = usage.limit_resets_count ?? null;

        function usageColor(pct, prefix) {
            return prefix + (pct > 80 ? 'red-400' : (pct > 50 ? 'cyber-amber' : 'cyber-green'));
        }
        function remainingColor(pct, prefix) {
            return prefix + (pct < 20 ? 'red-400' : (pct < 50 ? 'cyber-amber' : 'cyber-green'));
        }
        function spendColor(pct, prefix) {
            return prefix + (pct > 90 ? 'red-400' : (pct > 70 ? 'cyber-amber' : 'cyber-blue'));
        }
        function recolor(selector, color) {
            const el = document.querySelector(selector);
            if (el) el.className = el.className.replace(/\b(text|bg)-(red-400|cyber-\w+|gray-500)\b/g, '').trim() + ' ' + color;
        }
        function setBar(key, pct, color) {
            const bar = document.querySelector('[data-bar="' + key + '"]');
            if (bar) { bar.style.width = Math.min(pct, 100) + '%'; bar.className = 'h-1.5 rounded-full ' + color; }
        }
        function toggleCard(key, visible) {
            const card = document.querySelector('[data-usage-card="' + key + '"]');
            if (card) card.classList.toggle('hidden', !visible);
        }

        const plan = document.querySelector('[data-field="usage_plan"]');
        if (plan) { plan.textContent = usage.subscription_type ?? ''; plan.classList.toggle('hidden', !usage.subscription_type); }

        [['usage_five_hour', fiveH], ['usage_seven_day', sevenD]].forEach(([key, pct]) => {
            setField(key, pct + '%');
            setBar(key, pct, usageColor(pct, 'bg-'));
            recolor('[data-field="' + key + '"]', usageColor(pct, 'text-'));
        });

        toggleCard('cloud_credits', cloudLeft !== null);
        if (cloudLeft !== null) {
            setField('usage_cloud_credits', fmtUsd(cloudLeft) + ' ' + TRANSLATIONS.of + ' ' + fmtUsd(cloudTotal ?? 0));
            setBar('usage_cloud_credits', 100 - cloudPct, remainingColor(cloudPct, 'bg-'));
            recolor('[data-field="usage_cloud_credits"]', remainingColor(cloudPct, 'text-'));
            setField('usage_cloud_expires', usage.cloud_credits_expires_at
                ? TRANSLATIONS.expires + ' ' + new Date(usage.cloud_credits_expires_at).toLocaleString(LOCALE, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
                : '');
        }

        setField('usage_balance', fmtUsd(balanceCents / 100));
        const enabled = document.querySelector('[data-field="usage_credits_enabled"]');
        if (enabled) {
            const on = usage.usage_credits_enabled;
            enabled.textContent = on ? TRANSLATIONS.enabled : TRANSLATIONS.disabled;
            enabled.className = 'inline-block text-xs mt-1 px-1.5 rounded '
                + (on ? 'bg-cyber-green/20 text-cyber-green' : 'bg-gray-800 text-gray-500')
                + (on === null || on === undefined ? ' hidden' : '');
        }

        toggleCard('monthly_spend', !!extraLimit);
        if (extraLimit) {
            setField('usage_extra', fmtUsd(extraUsed / 100) + ' ' + TRANSLATIONS.of + ' ' + fmtUsd(extraLimit / 100));
            setBar('usage_extra', extraPct, spendColor(extraPct, 'bg-'));
            recolor('[data-field="usage_extra"]', spendColor(extraPct, 'text-'));
        }

        toggleCard('limit_resets', limitResets !== null);
        setField('usage_limit_resets', limitResets ? String(limitResets) : TRANSLATIONS.no_limit_resets);
        recolor('[data-field="usage_limit_resets"]', limitResets ? 'text-cyber-green' : 'text-gray-500');

        if (usage.snapshot_at) setField('usage_snapshot', TRANSLATIONS.snapshot_at + ': ' + relativeTime(usage.snapshot_at));

        const periodMap = {
            five_hour:        { resetsAt: usage.five_hour_resets_at,        seconds: 5 * 3600 },
            seven_day:        { resetsAt: usage.seven_day_resets_at,        seconds: 7 * 86400 },
        };
        const usageMap = { five_hour: fiveH, seven_day: sevenD };
        Object.entries(periodMap).forEach(([key, { resetsAt, seconds }]) => {
            const section = document.querySelector('[data-time-section="' + key + '"]');
            if (!resetsAt) {
                if (section) section.classList.add('hidden');
                return;
            }
            if (section) section.classList.remove('hidden');
            const remaining = Math.max(0, Math.floor((new Date(resetsAt) - new Date()) / 1000));
            const elapsed = Math.min(100, Math.max(0, (seconds - remaining) / seconds * 100));
            const bar = document.querySelector('[data-bar="time_' + key + '"]');
            if (bar) bar.style.width = elapsed + '%';
            const txt = document.querySelector('[data-field="time_remaining_' + key + '"]');
            if (txt) {
                const d = Math.floor(remaining / 86400);
                const h = Math.floor((remaining % 86400) / 3600);
                const m = Math.floor((remaining % 3600) / 60);
                const label = d > 0 ? `${d}d ${h}t` : (h > 0 ? `${h}t ${m}m` : (m > 0 ? `${m}m` : '< 1m'));
                txt.textContent = label + ' ' + TRANSLATIONS.remaining;
            }
            const usage = usageMap[key] ?? 0;
            const diff = usage - elapsed;
            const pos  = usage >= 100 ? 100 : Math.min(100, Math.max(0, 50 + diff));
            const color = usage >= 100 ? 'bg-red-400' : (diff <= 0 ? 'bg-cyber-green' : (diff <= 15 ? 'bg-cyber-amber' : 'bg-red-400'));
            const fillLeft  = diff <= 0 ? pos  : 50;
            const fillWidth = Math.abs(pos - 50);
            const fill = document.querySelector('[data-bar="pace_fill_' + key + '"]');
            if (fill) {
                fill.style.left  = fillLeft + '%';
                fill.style.width = fillWidth + '%';
                fill.className = 'absolute inset-y-0 rounded opacity-60 ' + color;
            }
            const marker = document.querySelector('[data-bar="pace_marker_' + key + '"]');
            if (marker) {
                marker.style.left = pos + '%';
                marker.className = 'absolute inset-y-0 w-1 rounded-sm ' + color;
            }
        });
    }

    function sessionStatus(lastSeenAt) {
        if (!lastSeenAt) return { dot: 'bg-gray-600', label: TRANSLATIONS.inactive };
        const sec = Math.abs(Math.round((Date.now() - new Date(lastSeenAt).getTime()) / 1000));
        if (sec < 60) return { dot: 'bg-cyber-green animate-pulse', label: TRANSLATIONS.working };
        if (sec < 1800) return { dot: 'bg-yellow-500', label: TRANSLATIONS.idle + ' ' + Math.round(sec / 60) + 'm' };
        return { dot: 'bg-gray-600', label: TRANSLATIONS.inactive };
    }

    const GROUP_COLORS = ['border-cyan-500', 'border-purple-500', 'border-amber-500', 'border-emerald-500', 'border-rose-500', 'border-indigo-500', 'border-lime-500', 'border-fuchsia-500'];

    function renderSessionsTable(sessions) {
        const el = document.getElementById('sessions-table');
        if (!el || !sessions || sessions.length === 0) {
            if (el) el.innerHTML = '<p class="text-gray-500 text-sm">' + esc(TRANSLATIONS.no_sessions) + '</p>';
            return;
        }
        const csrfToken = document.querySelector('meta[name=csrf-token]');
        if (!csrfToken) return;

        const groupColorMap = {};
        let colorIdx = 0;

        let html = '<table class="w-full text-sm"><thead><tr class="text-gray-500 text-left">';
        html += '<th class="pb-2 w-8"></th><th class="pb-2">' + esc(TRANSLATIONS.session_id) + '</th><th class="pb-2">' + esc(TRANSLATIONS.project) + '</th><th class="pb-2">' + esc(TRANSLATIONS.email) + '</th><th class="pb-2">' + esc(TRANSLATIONS.terminal) + '</th><th class="pb-2">' + esc(TRANSLATIONS.hostname) + '</th><th class="pb-2">' + esc(TRANSLATIONS.version) + '</th><th class="pb-2 text-right">' + esc(TRANSLATIONS.last_seen) + '</th><th class="pb-2 text-right">' + esc(TRANSLATIONS.actions) + '</th>';
        html += '</tr></thead><tbody>';
        sessions.forEach(s => {
            const st = sessionStatus(s.last_seen_at);
            const sid = s.session_id || '';
            const shortId = sid.length > 24 ? sid.substring(0, 24) + '...' : sid;
            const gid = s.session_group_id;
            const hasGroup = s.group_size !== null && s.group_size !== undefined && s.group_size >= 2;
            const isCollapsed = s.group_collapsed || false;
            const isGroupHead = hasGroup && !isCollapsed;
            let groupBorder = '';
            if (hasGroup && gid) {
                if (!groupColorMap[gid]) {
                    groupColorMap[gid] = GROUP_COLORS[colorIdx % GROUP_COLORS.length];
                    colorIdx++;
                }
                groupBorder = ' border-l-3 ' + groupColorMap[gid];
            }
            const isExpanded = expandedGroups.has(gid);
            const hiddenClass = (isCollapsed && !isExpanded) ? ' hidden' : '';
            const groupBadge = (hasGroup && s.group_index < s.group_size) ? ' <span class="ml-1 text-[10px] text-gray-500 font-mono">' + s.group_index + '/' + s.group_size + '</span>' : '';
            const toggleBtn = isGroupHead ? '<button onclick="toggleGroup(\'' + gid + '\')" class="mr-1 text-gray-500 hover:text-gray-300 text-xs font-mono transition" data-group-toggle="' + gid + '">' + (isExpanded ? '▼' : '▶') + ' ' + s.group_size + '</button>' : '';
            const dataAttrs = hasGroup ? ' data-group="' + gid + '" data-group-collapsed="' + (isCollapsed ? '1' : '0') + '"' : '';
            html += '<tr class="border-t border-gray-800 hover:bg-gray-900/50' + groupBorder + hiddenClass + '"' + dataAttrs + '>';
            html += '<td class="py-2 text-center"><span class="w-2.5 h-2.5 rounded-full ' + st.dot + ' inline-block" title="' + esc(st.label) + '"></span></td>';
            html += '<td class="py-2">' + toggleBtn + '<a href="/sessions/' + encodeURIComponent(sid) + '" class="text-cyber-blue hover:underline font-mono text-xs">' + esc(shortId) + '</a>' + groupBadge + '</td>';
            html += '<td class="py-2 text-cyber-green font-semibold text-sm">' + esc(s.project_name || '-') + '</td>';
            html += '<td class="py-2 text-gray-400">' + esc(s.user_email || '-') + '</td>';
            html += '<td class="py-2 text-gray-400">' + esc(s.terminal_type || '-') + '</td>';
            html += '<td class="py-2 text-gray-400 text-xs">' + esc(s.hostname || '-') + '</td>';
            html += '<td class="py-2 text-gray-400 font-mono text-xs">' + esc(s.app_version || '-') + '</td>';
            html += '<td class="py-2 text-right text-gray-400">' + esc(relativeTime(s.last_seen_at)) + '</td>';
            html += '<td class="py-2 text-right flex items-center justify-end gap-2">';
            html += '<button type="button" onclick="startGrouping(\'' + sid + '\')" class="text-cyan-400/60 hover:text-cyan-400 transition text-xs group-btn" data-session-id="' + sid + '">' + esc(TRANSLATIONS.group) + '</button>';
            html += '<form method="POST" action="/sessions/' + encodeURIComponent(sid) + '" onsubmit="return confirm(\'' + esc(TRANSLATIONS.delete_confirm.replace('__ID__', sid)).replace(/'/g, "\\'") + '\')" class="inline"><input type="hidden" name="_token" value="' + csrfToken.content + '"><input type="hidden" name="_method" value="DELETE"><button type="submit" class="text-red-400/60 hover:text-red-400 transition text-xs">' + esc(TRANSLATIONS.delete) + '</button></form></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    const EVENT_COLORS = {
        api_request: 'text-blue-400', api_error: 'text-red-400',
        tool_result: 'text-green-400', user_prompt: 'text-yellow-400',
        tool_decision: 'text-purple-400'
    };

    function renderRecentEvents(events) {
        const el = document.getElementById('recent-events');
        if (!el || !events || events.length === 0) {
            if (el) el.innerHTML = '<p class="text-gray-500 text-sm">' + esc(TRANSLATIONS.no_events) + '</p>';
            return;
        }
        let html = '<table class="w-full text-sm"><thead class="sticky top-0 bg-gray-950"><tr class="text-gray-500 text-left">';
        html += '<th class="pb-2">' + esc(TRANSLATIONS.time) + '</th><th class="pb-2">' + esc(TRANSLATIONS.event) + '</th><th class="pb-2">' + esc(TRANSLATIONS.session) + '</th><th class="pb-2">' + esc(TRANSLATIONS.details) + '</th>';
        html += '</tr></thead><tbody>';
        events.forEach(ev => {
            const baseName = (ev.event_name || '').replace('claude_code.', '');
            const color = EVENT_COLORS[baseName] || 'text-gray-400';
            const attrs = ev.attributes || {};
            const details = [attrs.tool_name, attrs.model, attrs.cost_usd ? '$' + Number(attrs.cost_usd).toLocaleString(LOCALE, {minimumFractionDigits: 4, maximumFractionDigits: 4}) : null, attrs.duration_ms ? attrs.duration_ms + 'ms' : null].filter(Boolean).join(' | ');
            const time = ev.recorded_at ? new Date(ev.recorded_at).toLocaleTimeString(LOCALE, {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false}).replace(/\./g, ':') : '-';
            const shortSid = ev.session_id ? ev.session_id.substring(0, 12) : '-';
            html += '<tr class="border-t border-gray-800">';
            html += '<td class="py-1.5 text-gray-500 font-mono text-xs whitespace-nowrap">' + esc(time) + '</td>';
            html += '<td class="py-1.5 ' + color + ' font-mono text-xs">' + esc(baseName) + '</td>';
            html += '<td class="py-1.5 text-gray-500 font-mono text-xs">' + esc(shortSid) + '</td>';
            html += '<td class="py-1.5 text-gray-400 text-xs">' + esc(details || '-') + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        el.innerHTML = html;
    }

    let dashboardInterval;

    function updateDashboard() {
        fetch('/api/dashboard-data')
            .then(response => {
                if (!response.ok) {
                    fetch('/api/connectivity-error', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        body: JSON.stringify({ http_status: response.status }),
                    });
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.billingModel) billingModel = data.billingModel;
                const s = data.summary;
                setField('total_sessions', fmt(s.total_sessions));
                setField('active_sessions', s.active_sessions);
                setField('total_cost', fmtUsd(s.total_cost));
                setField('total_tokens', fmt(s.total_tokens));
                setField('total_active_time', fmtTime(s.total_active_time));
                setField('total_commits', s.total_commits);
                setField('total_prs', s.total_prs);
                setField('loc_added', '+' + fmt(data.locBreakdown.added));
                setField('loc_removed', '-' + fmt(data.locBreakdown.removed));
                setField('tokens_input', fmt(data.tokenBreakdown.input));
                setField('tokens_output', fmt(data.tokenBreakdown.output));
                setField('api_requests', fmt(s.api_requests));
                setField('api_errors', s.api_errors);

                setField('api_total_requests', fmt(data.apiPerformance.total_requests));
                setField('api_avg_duration', data.apiPerformance.avg_duration_ms + 'ms');
                setField('api_total_errors', data.apiPerformance.total_errors);
                setField('api_error_rate', data.apiPerformance.error_rate + '%');

                if (data.sessions) {
                    renderSessionsTable(data.sessions);
                    if (groupingSource) startGrouping(groupingSource);
                }
                if (data.recentEvents) renderRecentEvents(data.recentEvents);

                updateClaudeUsage(data.claudeUsage);

                const updateEl = document.getElementById('last-update');
                const dotEl = document.getElementById('status-dot');
                const textEl = document.getElementById('status-text');
                if (updateEl) updateEl.textContent = TRANSLATIONS.updated + ': ' + new Date().toLocaleTimeString(LOCALE, {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false}).replace(/\./g, ':');
                if (dotEl) dotEl.className = 'w-2 h-2 rounded-full bg-cyber-green animate-pulse';
                if (textEl) textEl.textContent = TRANSLATIONS.live;
                document.getElementById('disconnect-banner')?.classList.replace('visible', 'invisible');
            })
            .catch((err) => {
                // HTTP errors are already logged in the .then() — only log true network errors here
                if (!err || !String(err.message).startsWith('HTTP ')) {
                    fetch('/api/connectivity-error', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        body: JSON.stringify({ http_status: 0 }),
                    });
                }
                const dotEl = document.getElementById('status-dot');
                const textEl = document.getElementById('status-text');
                if (dotEl) dotEl.className = 'w-2 h-2 rounded-full bg-red-500';
                if (textEl) textEl.textContent = TRANSLATIONS.disconnected;
                document.getElementById('disconnect-banner')?.classList.replace('invisible', 'visible');
            });
    }

    dashboardInterval = setInterval(updateDashboard, REFRESH_INTERVAL);
    window.addEventListener('beforeunload', () => clearInterval(dashboardInterval));

    const initEl = document.getElementById('last-update');
    if (initEl) initEl.textContent = TRANSLATIONS.updated + ': ' + new Date().toLocaleTimeString(LOCALE, {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false}).replace(/\./g, ':');
</script>
@endsection
