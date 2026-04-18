@php use App\Helpers\Format; @endphp
@extends('layouts.app')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('dashboard') }}" class="text-cyber-blue hover:underline text-sm">&larr; {{ __('dashboard.back_to_dashboard') }}</a>
</div>

<div class="bg-panel border border-panel-border rounded-lg p-5">
    <h2 class="text-sm font-semibold text-red-400 uppercase tracking-wider mb-4">{{ __('dashboard.connectivity_errors_title') }} ({{ $errors->count() }})</h2>

    @if($errors->isEmpty())
        <p class="text-gray-500 text-sm">{{ __('dashboard.no_connectivity_errors') }}</p>
    @else
        <div class="overflow-y-auto" style="max-height: 800px;">
            <table class="w-full text-sm">
                <thead class="sticky top-0 bg-gray-950">
                    <tr class="text-gray-500 text-left">
                        <th class="pb-2">{{ __('dashboard.time') }}</th>
                        <th class="pb-2">HTTP</th>
                        <th class="pb-2">{{ __('dashboard.endpoint') }}</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($errors as $err)
                    @php
                        $statusStyle = match(true) {
                            $err->http_status === 504 => 'text-red-400',
                            $err->http_status >= 500  => 'text-red-400',
                            $err->http_status >= 400  => 'text-yellow-400',
                            $err->http_status === 0 || $err->http_status === null => 'text-yellow-400',
                            default => 'text-blue-400',
                        };
                        $statusLabel = $err->http_status === 0 || $err->http_status === null
                            ? __('dashboard.network_error')
                            : $err->http_status;
                    @endphp
                    <tr class="border-t border-gray-800">
                        <td class="py-1.5 text-gray-500 font-mono text-xs whitespace-nowrap">{{ Format::dateTime($err->created_at, 'date_time_short') }}</td>
                        <td class="py-1.5 font-mono text-xs {{ $statusStyle }}">{{ $statusLabel }}</td>
                        <td class="py-1.5 text-gray-400 text-xs font-mono">{{ $err->endpoint }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
