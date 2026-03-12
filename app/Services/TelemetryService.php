<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Support\Facades\DB;

class TelemetryService
{
    public function deleteSession(string $sessionId): bool
    {
        return (bool) TelemetrySession::where('session_id', $sessionId)->delete();
    }

    public function mergeSessions(string $sourceId, string $targetId): void
    {
        $source = TelemetrySession::where('session_id', $sourceId)->firstOrFail();
        $target = TelemetrySession::where('session_id', $targetId)->firstOrFail();

        DB::transaction(function () use ($source, $target, $sourceId, $targetId) {
            TelemetryMetric::where('session_id', $sourceId)->update(['session_id' => $targetId]);
            TelemetryEvent::where('session_id', $sourceId)->update(['session_id' => $targetId]);

            $target->update([
                'first_seen_at' => min($target->first_seen_at, $source->first_seen_at),
                'last_seen_at' => max($target->last_seen_at, $source->last_seen_at),
            ]);

            $source->delete();
        });
    }

    public function resetAll(): void
    {
        DB::transaction(function () {
            TelemetryEvent::truncate();
            TelemetryMetric::truncate();
            TelemetrySession::truncate();
        });
    }
}
