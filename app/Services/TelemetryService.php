<?php

namespace App\Services;

use App\Models\TelemetryEvent;
use App\Models\TelemetryMetric;
use App\Models\TelemetrySession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    public function ungroupSession(string $sessionId): void
    {
        $session = TelemetrySession::where('session_id', $sessionId)->firstOrFail();
        $groupId = $session->session_group_id;

        if (! $groupId) {
            return;
        }

        $session->update(['session_group_id' => null]);

        $remaining = TelemetrySession::where('session_group_id', $groupId)->count();
        if ($remaining === 1) {
            TelemetrySession::where('session_group_id', $groupId)->update(['session_group_id' => null]);
        }
    }

    public function groupSessions(string $sessionIdA, string $sessionIdB): void
    {
        $a = TelemetrySession::where('session_id', $sessionIdA)->firstOrFail();
        $b = TelemetrySession::where('session_id', $sessionIdB)->firstOrFail();

        $groupId = $a->session_group_id ?? $b->session_group_id ?? (string) Str::ulid();

        DB::transaction(function () use ($a, $b, $groupId) {
            if ($a->session_group_id && $b->session_group_id && $a->session_group_id !== $b->session_group_id) {
                TelemetrySession::where('session_group_id', $b->session_group_id)
                    ->update(['session_group_id' => $groupId]);
            }

            $a->update(['session_group_id' => $groupId]);
            $b->update(['session_group_id' => $groupId]);
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
