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

    public function test_new_session_gets_session_group_id(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload());

        $session = TelemetrySession::first();
        $this->assertNotNull($session->session_group_id);
    }

    public function test_new_session_within_window_groups_with_recent_session(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload(['session_id' => 'session-a']));

        $sessionA = TelemetrySession::where('session_id', 'session-a')->first();
        $sessionA->update(['last_seen_at' => now()->subMinutes(2)]);

        $payload = $this->metricsPayload(['session_id' => 'session-b']);
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] =
            ['key' => 'user.id', 'value' => ['stringValue' => 'user-1']];

        // Add user.id to first session too
        $sessionA->update(['user_id' => 'user-1']);

        // Also add user.id to payload for session-b
        $payloadB = $this->metricsPayload(['session_id' => 'session-b']);
        $payloadB['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] =
            ['key' => 'user.id', 'value' => ['stringValue' => 'user-1']];

        $this->postJson('/v1/metrics', $payloadB);

        $sessionA->refresh();
        $sessionB = TelemetrySession::where('session_id', 'session-b')->first();

        $this->assertNotNull($sessionA->session_group_id);
        $this->assertNotNull($sessionB->session_group_id);
        $this->assertSame($sessionA->session_group_id, $sessionB->session_group_id);
    }

    public function test_new_session_outside_window_gets_different_group(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload(['session_id' => 'session-old']));

        $sessionOld = TelemetrySession::where('session_id', 'session-old')->first();
        $sessionOld->update(['last_seen_at' => now()->subMinutes(10)]);

        $this->postJson('/v1/metrics', $this->metricsPayload(['session_id' => 'session-new']));

        $sessionOld->refresh();
        $sessionNew = TelemetrySession::where('session_id', 'session-new')->first();

        $this->assertNotSame($sessionOld->session_group_id, $sessionNew->session_group_id);
    }

    public function test_new_session_different_project_not_grouped(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload(['session_id' => 'session-proj-a']));

        $sessionA = TelemetrySession::where('session_id', 'session-proj-a')->first();
        $sessionA->update(['last_seen_at' => now()->subMinutes(1)]);

        $payloadB = $this->metricsPayload(['session_id' => 'session-proj-b']);
        $payloadB['resourceMetrics'][0]['resource']['attributes'][1]['value']['stringValue'] = 'other-project';
        $this->postJson('/v1/metrics', $payloadB);

        $sessionA->refresh();
        $sessionB = TelemetrySession::where('session_id', 'session-proj-b')->first();

        $this->assertNotSame($sessionA->session_group_id, $sessionB->session_group_id);
    }

    public function test_session_without_user_identifiers_not_grouped(): void
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
                                    'name' => 'claude_code.cost.usage',
                                    'unit' => 'USD',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 0.01,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => [
                                                    ['key' => 'session.id', 'value' => ['stringValue' => 'no-user-session']],
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

        $session = TelemetrySession::where('session_id', 'no-user-session')->first();
        $this->assertNull($session->session_group_id);
    }

    public function test_upsert_does_not_change_existing_group_id(): void
    {
        $this->postJson('/v1/metrics', $this->metricsPayload());

        $session = TelemetrySession::first();
        $originalGroupId = $session->session_group_id;

        $this->postJson('/v1/metrics', $this->metricsPayload(['value' => 0.05]));

        $session->refresh();
        $this->assertSame($originalGroupId, $session->session_group_id);
    }

    public function test_ingest_metrics_with_zero_timestamp_uses_now(): void
    {
        $payload = $this->metricsPayload();
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['timeUnixNano'] = '0';

        $this->postJson('/v1/metrics', $payload);

        $metric = TelemetryMetric::first();
        $this->assertNotNull($metric->recorded_at);
        $this->assertSame(now()->year, $metric->recorded_at->year);
    }

    public function test_ingest_metrics_with_null_attribute_key_is_skipped(): void
    {
        $payload = $this->metricsPayload();
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] = [
            'value' => ['stringValue' => 'orphan-value'],
        ];

        $response = $this->postJson('/v1/metrics', $payload);

        $response->assertOk();
        $metric = TelemetryMetric::first();
        $attrs = $metric->attributes ?? [];
        $this->assertNotContains('orphan-value', $attrs);
    }

    public function test_ingest_metrics_histogram_type(): void
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
                                    'name' => 'claude_code.response.duration',
                                    'unit' => 'ms',
                                    'histogram' => [
                                        'dataPoints' => [
                                            [
                                                'sum' => 1500.0,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => [
                                                    ['key' => 'session.id', 'value' => ['stringValue' => 'hist-session']],
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
            'session_id' => 'hist-session',
            'metric_name' => 'claude_code.response.duration',
            'metric_type' => 'histogram',
        ]);
    }

    public function test_ingest_metrics_truncates_long_attribute_values(): void
    {
        $payload = $this->metricsPayload();
        $longValue = str_repeat('x', 1500);
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] = [
            'key' => 'long_attr',
            'value' => ['stringValue' => $longValue],
        ];

        $this->postJson('/v1/metrics', $payload);

        $metric = TelemetryMetric::first();
        $this->assertSame(1000, strlen($metric->attributes['long_attr']));
    }

    public function test_ingest_logs_with_body_object_is_json_encoded(): void
    {
        $payload = $this->logsPayload();
        $payload['resourceLogs'][0]['scopeLogs'][0]['logRecords'][0]['body'] = ['mapValue' => ['key' => 'val']];

        $response = $this->postJson('/v1/logs', $payload);

        $response->assertOk();
        $event = TelemetryEvent::first();
        $this->assertJson($event->body);
    }

    public function test_ingest_metrics_without_session_id_generates_one(): void
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
                                    'name' => 'claude_code.cost.usage',
                                    'unit' => 'USD',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 0.01,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => [],
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

        $session = TelemetrySession::first();
        $this->assertStringStartsWith('unknown-', $session->session_id);
    }

    public function test_ingest_metrics_catches_exception_on_malformed_payload(): void
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
                                    'name' => 'test',
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asDouble' => 1.0,
                                                'timeUnixNano' => '1741776000000000000',
                                                'attributes' => 'not-an-array',
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

        $response = $this->postJson('/v1/metrics', $payload);

        $response->assertOk();
        $response->assertJsonPath('partialSuccess.rejectedDataPoints', 1);
    }

    public function test_ingest_logs_catches_exception_on_malformed_payload(): void
    {
        $payload = [
            'resourceLogs' => [
                [
                    'resource' => ['attributes' => []],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'logRecords' => [
                                [
                                    'timeUnixNano' => '1741776000000000000',
                                    'attributes' => 'not-an-array',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/v1/logs', $payload);

        $response->assertOk();
        $response->assertJsonPath('partialSuccess.rejectedLogRecords', 1);
    }

    public function test_new_session_groups_with_recent_session_without_group_id(): void
    {
        // Manually create a session with no group_id but with user identifiers
        TelemetrySession::create([
            'session_id' => 'session-no-group',
            'session_group_id' => null,
            'user_email' => 'test@example.com',
            'user_id' => 'user-1',
            'project_name' => 'my-project',
            'first_seen_at' => now()->subMinutes(1),
            'last_seen_at' => now()->subMinutes(1),
        ]);

        // Send a new session from the same user/project within the grouping window
        $payload = $this->metricsPayload(['session_id' => 'session-new-group']);
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] =
            ['key' => 'user.id', 'value' => ['stringValue' => 'user-1']];

        $this->postJson('/v1/metrics', $payload);

        $old = TelemetrySession::where('session_id', 'session-no-group')->first();
        $new = TelemetrySession::where('session_id', 'session-new-group')->first();

        // Both should now share the same group_id
        $this->assertNotNull($old->session_group_id);
        $this->assertNotNull($new->session_group_id);
        $this->assertSame($old->session_group_id, $new->session_group_id);
    }

    public function test_ingest_metrics_with_bool_attribute(): void
    {
        $payload = $this->metricsPayload();
        $payload['resourceMetrics'][0]['scopeMetrics'][0]['metrics'][0]['sum']['dataPoints'][0]['attributes'][] = [
            'key' => 'is_cached',
            'value' => ['boolValue' => true],
        ];

        $this->postJson('/v1/metrics', $payload);

        $metric = TelemetryMetric::first();
        $this->assertTrue($metric->attributes['is_cached']);
    }
}
