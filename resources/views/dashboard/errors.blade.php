@php use App\Helpers\Format; @endphp
@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-cyber-blue hover:underline text-sm">&larr; {{ __('dashboard.back_to_dashboard') }}</a>
</div>

<div class="bg-panel border border-panel-border rounded-lg p-5">
    <h2 class="text-sm font-semibold text-red-400 uppercase tracking-wider mb-4">{{ __('dashboard.error_panel_title') }} ({{ $errors->count() }})</h2>

    @if($errors->isEmpty())
        <p class="text-gray-500 text-sm">{{ __('dashboard.no_errors') }}</p>
    @else
        <div class="overflow-y-auto" style="max-height: 800px;">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-950">
                    <tr class="text-gray-500 text-left">
                        <th class="pb-2">{{ __('dashboard.time') }}</th>
                        <th class="pb-2">{{ __('dashboard.session') }}</th>
                        <th class="pb-2">{{ __('dashboard.project') }}</th>
                        <th class="pb-2">{{ __('dashboard.error_message') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($errors as $err)
                    @php
                        $errAttrs = $err->attributes ?? [];
                        $errId = 'err-' . $loop->index;
                        $errSeverityStyle = match(strtolower($err->severity ?? 'error')) {
                            'error' => 'bg-red-500/20 text-red-400 border border-red-500/30',
                            'warn', 'warning' => 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30',
                            default => 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
                        };
                    @endphp
                    <tr class="border-t border-gray-800 cursor-pointer hover:bg-gray-800/30 transition" onclick="toggleEventDetail('{{ $errId }}')">
                        <td class="py-1.5 text-gray-500 font-mono text-xs whitespace-nowrap">{{ Format::dateTime($err->recorded_at, 'date_time_short') }}</td>
                        <td class="py-1.5 text-xs">
                            @if($err->session)
                            <a href="{{ route('dashboard.session', $err->session->session_id) }}" class="text-cyber-blue hover:underline font-mono" onclick="event.stopPropagation()">{{ \Illuminate\Support\Str::limit($err->session->session_id, 16) }}</a>
                            @else
                            <span class="text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="py-1.5 text-gray-400 text-xs">{{ $err->session?->project_name ?? '-' }}</td>
                        <td class="py-1.5 text-red-400 text-xs">{{ \Illuminate\Support\Str::limit($errAttrs['error'] ?? $err->body ?? '-', 80) }}</td>
                    </tr>
                    <tr id="{{ $errId }}" class="hidden">
                        <td colspan="4" class="p-0">
                            <div class="mx-2 mb-2 p-3 bg-gray-900/60 border border-gray-700/50 rounded text-xs">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $errSeverityStyle }}">{{ $err->severity ?? 'ERROR' }}</span>
                                    <span class="text-red-400 font-mono">{{ $err->event_name }}</span>
                                    <span class="text-gray-500 font-mono">{{ Format::dateTime($err->recorded_at, 'time') }}</span>
                                </div>
                                @if($err->body)
                                <div class="mb-2">
                                    <p class="text-gray-500 text-[10px] uppercase mb-1">Body</p>
                                    <pre class="text-gray-300 bg-gray-950/50 rounded p-2 overflow-x-auto max-h-40 overflow-y-auto whitespace-pre-wrap break-all">{{ $err->body }}</pre>
                                </div>
                                @endif
                                @if(!empty($errAttrs))
                                <div>
                                    <p class="text-gray-500 text-[10px] uppercase mb-1">Attributes</p>
                                    <div class="space-y-0.5">
                                        @foreach($errAttrs as $key => $val)
                                        <div class="font-mono">
                                            <span class="text-cyber-blue">{{ $key }}</span><span class="text-gray-600">=</span><span class="text-gray-300">{{ is_array($val) ? json_encode($val) : $val }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    function toggleEventDetail(id) {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('hidden');
    }
</script>
@endsection
