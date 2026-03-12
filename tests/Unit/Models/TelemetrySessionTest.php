<?php

namespace Tests\Unit\Models;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetrySessionTest extends TestCase
{
    use RefreshDatabase;

    private function createSession(string $sessionId = 'test-session'): TelemetrySession
    {
        return TelemetrySession::create([
            'session_id' => $sessionId,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);
    }

    public function test_ulid_is_generated_on_creation(): void
    {
        $session = $this->createSession();

        $this->assertNotNull($session->id);
        $this->assertSame(26, strlen($session->id));
    }

    public function test_metrics_relationship(): void
    {
        $session = $this->createSession();

        TelemetryMetric::create([
            'session_id' => 'test-session',
            'metric_name' => 'claude_code.cost.usage',
            'metric_type' => 'sum',
            'value' => 0.5,
            'recorded_at' => now(),
        ]);

        $this->assertCount(1, $session->metrics);
        $this->assertInstanceOf(TelemetryMetric::class, $session->metrics->first());
    }

    public function test_events_relationship(): void
    {
        $session = $this->createSession();

        TelemetryEvent::create([
            'session_id' => 'test-session',
            'event_name' => 'tool_result',
            'recorded_at' => now(),
        ]);

        $this->assertCount(1, $session->events);
        $this->assertInstanceOf(TelemetryEvent::class, $session->events->first());
    }

    public function test_cascade_deletes_metrics_and_events(): void
    {
        $session = $this->createSession();

        TelemetryMetric::create([
            'session_id' => 'test-session',
            'metric_name' => 'test.metric',
            'metric_type' => 'sum',
            'value' => 1,
            'recorded_at' => now(),
        ]);

        TelemetryEvent::create([
            'session_id' => 'test-session',
            'event_name' => 'test.event',
            'recorded_at' => now(),
        ]);

        $session->delete();

        $this->assertSame(0, TelemetryMetric::where('session_id', 'test-session')->count());
        $this->assertSame(0, TelemetryEvent::where('session_id', 'test-session')->count());
    }

    public function test_datetime_casts(): void
    {
        $session = $this->createSession();

        $this->assertInstanceOf(\Carbon\Carbon::class, $session->first_seen_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $session->last_seen_at);
    }

    public function test_grouped_sessions_relationship(): void
    {
        $sessionA = $this->createSession('session-a');
        $sessionA->update(['session_group_id' => 'group-1']);

        $sessionB = TelemetrySession::create([
            'session_id' => 'session-b',
            'session_group_id' => 'group-1',
            'first_seen_at' => now()->subMinutes(30),
            'last_seen_at' => now(),
        ]);

        $grouped = $sessionA->groupedSessions;

        $this->assertCount(2, $grouped);
        $this->assertTrue($grouped->contains('session_id', 'session-b'));
    }
}
