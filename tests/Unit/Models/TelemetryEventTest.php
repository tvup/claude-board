<?php

namespace Tests\Unit\Models;

use App\Models\TelemetryEvent;
use App\Models\TelemetrySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelemetryEventTest extends TestCase
{
    use RefreshDatabase;

    private function createSessionAndEvent(array $eventAttrs = []): TelemetryEvent
    {
        TelemetrySession::create([
            'session_id' => 'test-session',
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now(),
        ]);

        return TelemetryEvent::create(array_merge([
            'session_id' => 'test-session',
            'event_name' => 'tool_result',
            'severity' => 'INFO',
            'body' => 'test event body',
            'attributes' => ['tool_name' => 'Read', 'success' => 'true'],
            'recorded_at' => now(),
        ], $eventAttrs));
    }

    public function test_attributes_cast_to_array(): void
    {
        $event = $this->createSessionAndEvent();

        $this->assertIsArray($event->attributes);
        $this->assertSame('Read', $event->attributes['tool_name']);
    }

    public function test_recorded_at_cast_to_datetime(): void
    {
        $event = $this->createSessionAndEvent();

        $this->assertInstanceOf(\Carbon\Carbon::class, $event->recorded_at);
    }

    public function test_session_relationship(): void
    {
        $event = $this->createSessionAndEvent();

        $this->assertInstanceOf(TelemetrySession::class, $event->session);
        $this->assertSame('test-session', $event->session->session_id);
    }

    public function test_null_attributes_default(): void
    {
        $event = $this->createSessionAndEvent(['attributes' => null]);

        $this->assertNull($event->attributes);
    }

    public function test_fillable_fields(): void
    {
        $event = $this->createSessionAndEvent([
            'severity' => 'ERROR',
            'body' => 'error occurred',
        ]);

        $this->assertSame('ERROR', $event->severity);
        $this->assertSame('error occurred', $event->body);
    }
}
