<?php

namespace App\Http\Controllers;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OtlpController extends Controller
{
    private const SESSION_META_KEYS = [
        'session.id',
        'user.email',
        'user.id',
        'user.account_uuid',
        'organization.id',
        'terminal.type',
        'project.name',
        'billing.model',
    ];

    private const MAX_ATTRIBUTE_LENGTH = 1000;

    public function ingestMetrics(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        try {
            foreach ($payload['resourceMetrics'] ?? [] as $resourceMetric) {
                $resourceAttrs = $this->extractAttributes(
                    $resourceMetric['resource']['attributes'] ?? []
                );

                $sessionId = null;

                foreach ($resourceMetric['scopeMetrics'] ?? [] as $scopeMetric) {
                    foreach ($scopeMetric['metrics'] ?? [] as $metric) {
                        $name = $metric['name'];
                        $unit = $metric['unit'] ?? null;

                        $dataPoints = [];
                        $metricType = 'unknown';

                        if (isset($metric['sum'])) {
                            $dataPoints = $metric['sum']['dataPoints'] ?? [];
                            $metricType = 'sum';
                        } elseif (isset($metric['gauge'])) {
                            $dataPoints = $metric['gauge']['dataPoints'] ?? [];
                            $metricType = 'gauge';
                        } elseif (isset($metric['histogram'])) {
                            $dataPoints = $metric['histogram']['dataPoints'] ?? [];
                            $metricType = 'histogram';
                        }

                        foreach ($dataPoints as $dp) {
                            $value = $dp['asDouble'] ?? $dp['asInt'] ?? $dp['sum'] ?? 0;
                            $time = $this->nanoToCarbon($dp['timeUnixNano'] ?? $dp['startTimeUnixNano'] ?? '0');
                            $attrs = $this->extractAttributes($dp['attributes'] ?? []);

                            if ($sessionId === null) {
                                $merged = array_merge($resourceAttrs, $attrs);
                                $sessionId = $this->upsertSession($merged);
                            }

                            TelemetryMetric::create([
                                'session_id' => $sessionId,
                                'metric_name' => $name,
                                'metric_type' => $metricType,
                                'value' => (float) $value,
                                'unit' => $unit,
                                'attributes' => $this->cleanAttributes($attrs),
                                'recorded_at' => $time,
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('OTLP metrics ingestion failed', ['error' => $e->getMessage()]);

            return response()->json(['partialSuccess' => ['rejectedDataPoints' => 1, 'errorMessage' => 'Ingestion error']], 200);
        }

        return response()->json(['partialSuccess' => new \stdClass()]);
    }

    public function ingestLogs(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        try {
            foreach ($payload['resourceLogs'] ?? [] as $resourceLog) {
                $resourceAttrs = $this->extractAttributes(
                    $resourceLog['resource']['attributes'] ?? []
                );

                $sessionId = null;

                foreach ($resourceLog['scopeLogs'] ?? [] as $scopeLog) {
                    foreach ($scopeLog['logRecords'] ?? [] as $record) {
                        $attrs = $this->extractAttributes($record['attributes'] ?? []);

                        if ($sessionId === null) {
                            $merged = array_merge($resourceAttrs, $attrs);
                            $sessionId = $this->upsertSession($merged);
                        }

                        $eventName = $attrs['event.name'] ?? $record['body']['stringValue'] ?? 'unknown';
                        unset($attrs['event.name']);

                        $time = $this->nanoToCarbon($record['timeUnixNano'] ?? $record['observedTimeUnixNano'] ?? '0');
                        $body = null;
                        if (isset($record['body'])) {
                            $body = $record['body']['stringValue'] ?? json_encode($record['body']);
                        }
                        $severity = $record['severityText'] ?? null;

                        TelemetryEvent::create([
                            'session_id' => $sessionId,
                            'event_name' => $eventName,
                            'severity' => $severity,
                            'body' => $body,
                            'attributes' => $this->cleanAttributes($attrs),
                            'recorded_at' => $time,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('OTLP logs ingestion failed', ['error' => $e->getMessage()]);

            return response()->json(['partialSuccess' => ['rejectedLogRecords' => 1, 'errorMessage' => 'Ingestion error']], 200);
        }

        return response()->json(['partialSuccess' => new \stdClass()]);
    }

    private function extractAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $attr) {
            $key = $attr['key'] ?? null;
            $value = $attr['value'] ?? [];
            if ($key === null) {
                continue;
            }
            $resolved = $value['stringValue']
                ?? $value['intValue']
                ?? $value['doubleValue']
                ?? ($value['boolValue'] ?? null)
                ?? json_encode($value);

            if (is_string($resolved) && mb_strlen($resolved) > self::MAX_ATTRIBUTE_LENGTH) {
                $resolved = mb_substr($resolved, 0, self::MAX_ATTRIBUTE_LENGTH);
            }

            $result[$key] = $resolved;
        }

        return $result;
    }

    private function cleanAttributes(array $attrs): ?array
    {
        foreach (self::SESSION_META_KEYS as $key) {
            unset($attrs[$key]);
        }

        return $attrs ?: null;
    }

    private function upsertSession(array $attrs): string
    {
        $sessionId = $attrs['session.id'] ?? 'unknown-'.uniqid();
        $now = now();

        $meta = [
            'user_email' => $attrs['user.email'] ?? null,
            'user_id' => $attrs['user.id'] ?? null,
            'account_uuid' => $attrs['user.account_uuid'] ?? null,
            'organization_id' => $attrs['organization.id'] ?? null,
            'app_version' => $attrs['app.version'] ?? $attrs['service.version'] ?? null,
            'terminal_type' => $attrs['terminal.type'] ?? null,
            'project_name' => $attrs['project.name'] ?? null,
            'billing_model' => $attrs['billing.model'] ?? null,
        ];

        $session = TelemetrySession::where('session_id', $sessionId)->first();

        if ($session) {
            $session->update(array_filter($meta) + ['last_seen_at' => $now]);
        } else {
            TelemetrySession::create(
                ['session_id' => $sessionId, 'first_seen_at' => $now, 'last_seen_at' => $now] + $meta
            );
        }

        return $sessionId;
    }

    private function nanoToCarbon(string $nanoTimestamp): Carbon
    {
        if ($nanoTimestamp === '0' || empty($nanoTimestamp)) {
            return now();
        }

        $seconds = (int) ($nanoTimestamp / 1_000_000_000);

        return Carbon::createFromTimestamp($seconds);
    }
}
