<?php

namespace Tests\Unit\Models;

use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryMetricTest extends TestCase
{
    use RefreshDatabase;

    private function createSessionAndMetric(array $metricAttrs = []): TelemetryMetric
    {
        TelemetrySession::create([
            'session_id' => 'test-session',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        return TelemetryMetric::create(array_merge([
            'session_id' => 'test-session',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 0.0542,
            'unit' => 'USD',
            'attributes' => ['model' => 'claude-sonnet-4-5-20250514'],
            'recorded_at' => now(),
        ], $metricAttrs));
    }

    public function test_value_cast_to_double(): void
    {
        $metric = $this->createSessionAndMetric();

        $this->assertIsFloat($metric->value);
        $this->assertEqualsWithDelta(0.0542, $metric->value, 0.0001);
    }

    public function test_attributes_cast_to_array(): void
    {
        $metric = $this->createSessionAndMetric();

        $this->assertIsArray($metric->attributes);
        $this->assertSame('claude-sonnet-4-5-20250514', $metric->attributes['model']);
    }

    public function test_recorded_at_cast_to_datetime(): void
    {
        $metric = $this->createSessionAndMetric();

        $this->assertInstanceOf(\Carbon\Carbon::class, $metric->recorded_at);
    }

    public function test_session_relationship(): void
    {
        $metric = $this->createSessionAndMetric();

        $this->assertInstanceOf(TelemetrySession::class, $metric->session);
        $this->assertSame('test-session', $metric->session->session_id);
    }

    public function test_fillable_fields(): void
    {
        $metric = $this->createSessionAndMetric([
            'metric_name' => 'claude_code.token.usage',
            'metric_type' => 'sum',
            'value' => 5000,
            'unit' => 'tokens',
            'attributes' => ['type' => 'input'],
        ]);

        $this->assertSame('claude_code.token.usage', $metric->metric_name);
        $this->assertSame('tokens', $metric->unit);
        $this->assertEqualsWithDelta(5000.0, $metric->value, 0.1);
    }
}
