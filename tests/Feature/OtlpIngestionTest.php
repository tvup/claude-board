<?php

namespace Tests\Feature;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OtlpIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function metricsPayload(array $overrides = []): array
    {
        $sessionId = $overrides['session_id'] ?? 'test-session-123';
        $metricName = $overrides['metric_name'] ?? 'claude_code.cost.usage';
        $value = $overrides['value'] ?? 0.0042;

        return [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'claude-code']],
                            ['key' => 'project.name', 'value' => ['stringValue' => 'my-project']],
                        ],
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'metrics' => [
                                [
                                    'name' => $metricName,
                                    'unit' => 'USD',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asDouble' => $value,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => [
                                                    ['key' => 'session.id', 'value' => ['stringValue' => $sessionId]],
                                                    ['key' => 'user.email', 'value' => ['stringValue' => 'test@example.com']],
                                                    ['key' => 'billing.model', 'value' => ['stringValue' => 'subscription']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function logsPayload(array $overrides = []): array
    {
        $sessionId = $overrides['session_id'] ?? 'test-session-123';
        $eventName = $overrides['event_name'] ?? 'tool_result';

        return [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'claude-code']],
                        ],
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => '1741776000000000000',
                                    'severityText' => 'INFO',
                                    'body' => ['stringValue' => $eventName],
                                    'attributes' => [
                                        ['key' => 'session.id', 'value' => ['stringValue' => $sessionId]],
                                        ['key' => 'event.name', 'value' => ['stringValue' => $eventName]],
                                        ['key' => 'tool_name', 'value' => ['stringValue' => 'Read']],
                                        ['key' => 'success', 'value' => ['stringValue' => 'true']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_ingest_metrics_creates_session_and_metric(): void
    {
        $response = $this->postJson('/v1/metrics', $this->metricsPayload());

        $response->assertOk();
        $response->assertJsonStructure(['partialSuccess']);

        $this->assertDatabaseHas('telemetry_sessions', [
            'session_id' => 'test-session-123',
            'user_email' => 'test@example.com',
            'project_name' => 'my-project',
            'billing_model' => 'subscription',
        ]);

        $this->assertDatabaseHas('telemetry_metrics', [
            'session_id' => 'test-session-123',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
        ]);
    }

    public function test_ingest_metrics_upserts_existing_session(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload());
        $this->postJson('/v1/metrics', $this->metricsPayload(['value' => 0.01]));

        $this->assertSame(1, TelemetrySession::count());
        $this->assertSame(2, TelemetryMetric::count());
    }

    public function test_ingest_logs_creates_session_and_event(): void
    {
        $response = $this->postJson('/v1/logs', $this->logsPayload());

        $response->assertOk();

        $this->assertDatabaseHas('telemetry_sessions', [
            'session_id' => 'test-session-123',
        ]);

        $this->assertDatabaseHas('telemetry_events', [
            'session_id' => 'test-session-123',
            'event_name' => 'tool_result',
            'severity' => 'INFO',
        ]);

        $event = TelemetryEvent::first();
        $this->assertSame('Read', $event->attributes['tool_name']);
        $this->assertSame('true', $event->attributes['success']);
        $this->assertArrayNotHasKey('event.name', $event->attributes);
        $this->assertArrayNotHasKey('session.id', $event->attributes);
    }

    public function test_ingest_metrics_converts_nanosecond_timestamp(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload());

        $metric = TelemetryMetric::first();
        $this->assertNotNull($metric->recorded_at);
        $this->assertSame(2025, $metric->recorded_at->year);
    }

    public function test_ingest_metrics_handles_empty_payload(): void
    {
        $response = $this->postJson('/v1/metrics', []);
        $response->assertOk();
    }

    public function test_ingest_logs_handles_empty_payload(): void
    {
        $response = $this->postJson('/v1/logs', []);
        $response->assertOk();
    }

    public function test_ingest_metrics_extracts_gauge_data_points(): void
    {
        $payload = [
            'resourceMetrics' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'metrics' => [
                                [
                                    'name' => 'claude_code.active_time.total',
                                    'unit' => 's',
                                    'gauge' => [
                                        'dataPoints' => [
                                            [
                                                'asInt' => 3600,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => [
                                                    ['key' => 'session.id', 'value' => ['stringValue' => 'gauge-session']],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson('/v1/metrics', $payload);

        $this->assertDatabaseHas('telemetry_metrics', [
            'session_id' => 'gauge-session',
            'metric_name' => 'claude_code.active_time.total',
            'metric_type' => 'gauge',
            'value' => 3600,
        ]);
    }

    public function test_session_meta_keys_are_cleaned_from_metric_attributes(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload());

        $metric = TelemetryMetric::first();
        $attrs = $metric->attributes ?? [];
        $this->assertArrayNotHasKey('session.id', $attrs);
        $this->assertArrayNotHasKey('user.email', $attrs);
        $this->assertArrayNotHasKey('billing.model', $attrs);
    }
}
