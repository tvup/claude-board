@php use App\Helpers\Format; @endphp
@extends('layouts.app')

@section('content')
@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-600/20 border border-green-600/40 rounded-lg text-green-400 text-sm">
    {{ session('success') }}
</div>
@endif
@if(session('error'))
<div class="mb-4 px-4 py-3 bg-red-600/20 border border-red-600/40 rounded-lg text-red-400 text-sm">
    {{ session('error') }}
</div>
@endif

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-cyber-blue hover:underline text-sm">&larr; {{ __('dashboard.back_to_dashboard') }}</a>
    <div class="flex items-center gap-3">
        @if($otherSessions->isNotEmpty())
        <form method="POST" action="{{ route('dashboard.session.merge', $session->session_id) }}" onsubmit="return confirm('{{ __('dashboard.merge_confirm') }}')">
            @csrf
            <div class="flex items-center gap-2">
                <label class="text-gray-500 text-xs">{{ __('dashboard.merge_into') }}:</label>
                <select name="merge_into" class="bg-gray-800 border border-gray-700 text-gray-300 text-xs rounded px-2 py-1.5 focus:border-cyber-blue focus:outline-none">
                    @foreach($otherSessions as $other)
                        <option value="{{ $other->session_id }}">{{ \Illuminate\Support\Str::limit($other->session_id, 16) }} — {{ $other->project_name ?? __('dashboard.no_project') }} ({{ Format::dateTime($other->last_seen_at, 'date_time_short') }})</option>
                    @endforeach
                </select>
                <button type="submit" class="px-3 py-1.5 text-xs bg-cyber-blue/20 text-cyber-blue border border-cyber-blue/40 rounded hover:bg-cyber-blue/40 transition whitespace-nowrap">{{ __('dashboard.merge') }}</button>
            </div>
        </form>
        @endif
        <form method="POST" action="{{ route('dashboard.session.destroy', $session->session_id) }}" onsubmit="return confirm('{{ __('dashboard.delete_session_detail_confirm') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3 py-1.5 text-sm bg-red-600/20 text-red-400 border border-red-600/40 rounded hover:bg-red-600/40 transition">{{ __('dashboard.delete_session') }}</button>
        </form>
    </div>
</div>

{{-- Activity Status --}}
<div id="activity-card" class="bg-panel border border-panel-border rounded-lg p-5 mb-6">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">{{ __('dashboard.activity_status') }}</h2>
        <div id="activity-status" class="flex items-center gap-2">
            <span id="activity-dot" class="w-3 h-3 rounded-full bg-gray-600"></span>
            <span id="activity-label" class="text-sm text-gray-400">{{ __('dashboard.loading') }}</span>
        </div>
    </div>
    <div class="mb-4">
        <p class="text-gray-500 text-xs mb-1">{{ __('dashboard.current_activity') }}</p>
        <p id="activity-current" class="text-gray-200 text-sm font-mono">-</p>
    </div>
    <div class="grid grid-cols-3 gap-4 text-sm mb-4">
        <div>
            <p class="text-gray-500 text-xs">{{ __('dashboard.last_activity') }}</p>
            <p id="activity-last" class="text-gray-200 font-mono text-xs">-</p>
        </div>
        <div>
            <p class="text-gray-500 text-xs">{{ __('dashboard.events_5min') }}</p>
            <p id="activity-events-5min" class="text-gray-200 font-mono">-</p>
        </div>
        <div>
            <p class="text-gray-500 text-xs">{{ __('dashboard.rate_events_min') }}</p>
            <p id="activity-rate" class="text-gray-200 font-mono">-</p>
        </div>
    </div>
    <div class="mb-4">
        <p class="text-gray-500 text-xs mb-2">{{ __('dashboard.activity_trend') }}</p>
        <div id="activity-progress" class="flex items-end gap-1 h-8">
            @for($i = 0; $i < 5; $i++)
            <div class="flex-1 bg-gray-700 rounded-sm" style="height: 2px;"></div>
            @endfor
        </div>
    </div>
    <div>
        <p class="text-gray-500 text-xs mb-2">{{ __('dashboard.recent_activity') }}</p>
        <div id="activity-timeline" class="flex items-center gap-1 flex-wrap">
            <span class="text-gray-600 text-xs">{{ __('dashboard.no_data_yet') }}</span>
        </div>
    </div>
</div>

<div class="bg-panel border border-panel-border rounded-lg p-5 mb-6">
    <h2 class="text-lg font-semibold text-white mb-4">
        {{ __('dashboard.session') }}: <span class="text-cyber-green font-mono text-base">{{ $session->session_id }}</span>
    </h2>
    @php $isApi = ($session->billing_model ?? config('claude-board.billing_model', 'subscription')) === 'api'; @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <p class="text-gray-500">{{ __('dashboard.project') }}</p>
            <p class="text-cyber-green font-semibold">{{ $session->project_name ?? '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.email') }}</p>
            <p class="text-gray-200">{{ $session->user_email ?? '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.user_id') }}</p>
            <p class="text-gray-200 font-mono text-xs truncate" title="{{ $session->user_id ?? '-' }}">{{ $session->user_id ?? '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.version') }}</p>
            <p class="text-gray-200 font-mono">{{ $session->app_version ?? '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.terminal') }}</p>
            <p class="text-gray-200">{{ $session->terminal_type ?? '-' }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.first_seen') }}</p>
            <p class="text-gray-200">{{ Format::dateTime($session->first_seen_at) }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.last_seen') }}</p>
            <p class="text-gray-200">{{ Format::dateTime($session->last_seen_at) }}</p>
        </div>
        <div>
            <p class="text-gray-500" title="{{ __('dashboard.cost_tooltip_' . ($isApi ? 'api' : 'subscription')) }}">
                {{ __('dashboard.cost_field_' . ($isApi ? 'api' : 'subscription')) }}
            </p>
            <p class="text-cyber-amber font-bold">{{ Format::currency($cost) }}</p>
            <p class="text-xs {{ $isApi ? 'text-yellow-500/60' : 'text-cyber-green/60' }}">{{ __('dashboard.cost_sub_' . ($isApi ? 'api' : 'subscription')) }}</p>
        </div>
        <div>
            <p class="text-gray-500">{{ __('dashboard.tokens') }}</p>
            <p class="text-cyber-blue font-bold">{{ Format::number($tokens) }}</p>
        </div>
    </div>
</div>

{{-- Metrics --}}
@if($metrics->isNotEmpty())
<div class="bg-panel border border-panel-border rounded-lg p-5 mb-6">
    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.metrics') }} ({{ Format::number($metrics->count()) }})</h2>
    <div class="overflow-x-auto max-h-64 overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="sticky top-0 bg-gray-950"><tr class="text-gray-500 text-left"><th class="pb-2 pr-4">{{ __('dashboard.metric') }}</th><th class="pb-2 pr-2 text-right">{{ __('dashboard.value') }}</th><th class="pb-2 pr-4">{{ __('dashboard.unit') }}</th><th class="pb-2 pr-4">{{ __('dashboard.attributes') }}</th><th class="pb-2 text-right">{{ __('dashboard.time') }}</th></tr></thead>
            <tbody>
            @foreach($metrics as $m)
                <tr class="border-t border-gray-800">
                    <td class="py-1.5 pr-4 text-gray-300 font-mono text-xs whitespace-nowrap">{{ str_replace('claude_code.', '', $m->metric_name) }}</td>
                    <td class="py-1.5 pr-2 text-right text-white font-mono whitespace-nowrap">{{ $m->unit === 'USD' ? Format::currency($m->value, 4) : Format::number($m->value) }}</td>
                    <td class="py-1.5 pr-4 text-gray-500 text-xs whitespace-nowrap">{{ $m->unit ?? '-' }}</td>
                    <td class="py-1.5 text-gray-500 text-xs font-mono">
                        @if($m->attributes)
                            {{ collect($m->attributes)->map(fn($v, $k) => "$k=$v")->implode(', ') }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="py-1.5 text-right text-gray-500 text-xs">{{ Format::dateTime($m->recorded_at, 'time') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Events --}}
@if($events->isNotEmpty())
<div class="bg-panel border border-panel-border rounded-lg p-5">
    <h2 class="text-sm font-semibold text-gray-300 uppercase tracking-wider mb-3">{{ __('dashboard.events') }} ({{ Format::number($events->count()) }})</h2>
    <div class="overflow-x-auto max-h-96 overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="sticky top-0 bg-gray-950"><tr class="text-gray-500 text-left"><th class="pb-2">{{ __('dashboard.time') }}</th><th class="pb-2">{{ __('dashboard.event') }}</th><th class="pb-2">{{ __('dashboard.details') }}</th></tr></thead>
            <tbody>
            @foreach($events as $event)
                @php
                    $attrs = $event->attributes ?? [];
                    $details = collect([
                        isset($attrs['tool_name']) ? $attrs['tool_name'] : null,
                        isset($attrs['model']) ? $attrs['model'] : null,
                        isset($attrs['cost_usd']) ? Format::currency((float)$attrs['cost_usd'], 4) : null,
                        isset($attrs['duration_ms']) ? $attrs['duration_ms'].'ms' : null,
                        isset($attrs['success']) ? (($attrs['success'] === 'true' || $attrs['success'] === true) ? 'OK' : 'FAIL') : null,
                        isset($attrs['error']) ? \Illuminate\Support\Str::limit($attrs['error'], 60) : null,
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
                    <td class="py-1.5 text-gray-400 text-xs">{{ $details ?: '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

@section('scripts')
<script>
    const SESSION_ID = @json($session->session_id);
    const ACTIVITY_INTERVAL = 3000;
    const LOCALE = @json(app()->getLocale());
    @php $jsTranslations = ['working' => __('dashboard.working'), 'idle' => __('dashboard.idle'), 'inactive' => __('dashboard.inactive'), 'no_events_yet' => __('dashboard.no_events_yet')]; @endphp
    const TRANSLATIONS = @json($jsTranslations);

    function esc(str) {
        const d = document.createElement('div');
        d.appendChild(document.createTextNode(String(str)));
        return d.innerHTML;
    }

    const EVENT_COLORS = {
        api_request: { bg: 'bg-blue-500', border: 'border-blue-400', label: 'API' },
        api_error: { bg: 'bg-red-500', border: 'border-red-400', label: 'ERR' },
        tool_result: { bg: 'bg-green-500', border: 'border-green-400', label: 'Tool' },
        user_prompt: { bg: 'bg-yellow-500', border: 'border-yellow-400', label: 'User' },
        tool_decision: { bg: 'bg-purple-500', border: 'border-purple-400', label: 'Dec' },
    };

    const STATUS_STYLES = {
        working: { dot: 'bg-cyber-green animate-pulse', text: 'text-cyber-green', label: TRANSLATIONS.working },
        idle: { dot: 'bg-yellow-500', text: 'text-yellow-400', label: TRANSLATIONS.idle },
        inactive: { dot: 'bg-gray-600', text: 'text-gray-400', label: TRANSLATIONS.inactive },
    };

    function fmtInactivity(seconds) {
        if (seconds < 60) return seconds + 's';
        if (seconds < 3600) return Math.round(seconds / 60) + 'm';
        return Math.round(seconds / 3600) + 'h';
    }

    let activityInterval;

    function updateActivity() {
        fetch('/api/sessions/' + encodeURIComponent(SESSION_ID) + '/activity')
            .then(r => r.json())
            .then(data => {
                const style = STATUS_STYLES[data.status] || STATUS_STYLES.inactive;
                const dot = document.getElementById('activity-dot');
                const label = document.getElementById('activity-label');
                if (!dot || !label) return;

                dot.className = 'w-3 h-3 rounded-full ' + style.dot;
                let labelText = style.label;
                if (data.status !== 'working' && data.inactivity_seconds != null) {
                    labelText += ' ' + fmtInactivity(data.inactivity_seconds);
                }
                label.textContent = labelText;
                label.className = 'text-sm ' + style.text;

                const lastEl = document.getElementById('activity-last');
                if (lastEl) lastEl.textContent = data.last_activity_at
                    ? new Date(data.last_activity_at).toLocaleTimeString(LOCALE, {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false}).replace(/\./g, ':')
                    : '-';
                const eventsEl = document.getElementById('activity-events-5min');
                if (eventsEl) eventsEl.textContent = data.events_last_5min;
                const rateEl = document.getElementById('activity-rate');
                if (rateEl) rateEl.textContent = parseFloat(data.activity_rate).toLocaleString(LOCALE, {maximumFractionDigits: 1}) + '/min';
                const currentEl = document.getElementById('activity-current');
                if (currentEl) currentEl.textContent = data.current_activity || '-';

                // Progress buckets (mini bar chart) — only safe CSS classes and numeric values
                if (data.progress_buckets) {
                    const maxBucket = Math.max(1, ...data.progress_buckets);
                    const progressEl = document.getElementById('activity-progress');
                    if (progressEl) {
                        const colors = { working: 'bg-cyber-green', idle: 'bg-yellow-500' };
                        const color = colors[data.status] || 'bg-gray-600';
                        progressEl.innerHTML = data.progress_buckets.map((count, i) => {
                            const pct = Math.max(6, (Number(count) / maxBucket) * 100);
                            const opacity = count === 0 ? '0.3' : '1';
                            const title = esc((5 - i) + 'min ago: ' + Number(count) + ' events');
                            return '<div class="flex-1 ' + color + ' rounded-sm transition-all" style="height: ' + pct + '%; opacity: ' + opacity + ';" title="' + title + '"></div>';
                        }).join('');
                    }
                }

                const timeline = document.getElementById('activity-timeline');
                if (!timeline) return;
                if (!data.recent || data.recent.length === 0) {
                    timeline.textContent = TRANSLATIONS.no_events_yet;
                    timeline.className = 'text-gray-600 text-xs';
                } else {
                    timeline.className = 'flex items-center gap-1 flex-wrap';
                    timeline.innerHTML = data.recent.map(ev => {
                        const c = EVENT_COLORS[ev.name] || { bg: 'bg-gray-500', border: 'border-gray-400', label: ev.name };
                        const tooltip = esc(String(ev.name || '') + (ev.detail ? ': ' + ev.detail : '') + ' @ ' + (ev.time || ''));
                        return '<span class="w-4 h-4 rounded-full ' + c.bg + ' border ' + c.border +
                            ' inline-block cursor-default" title="' + tooltip + '"></span>';
                    }).join('');
                }
            })
            .catch(() => {});
    }

    updateActivity();
    activityInterval = setInterval(updateActivity, ACTIVITY_INTERVAL);
    window.addEventListener('beforeunload', () => clearInterval(activityInterval));
</script>
@endsection
