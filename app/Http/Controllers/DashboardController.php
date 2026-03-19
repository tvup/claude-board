<?php

namespace App\Http\Controllers;

use App\Models\TelemetrySession;
use App\Services\DashboardQueryService;
use App\Services\TelemetryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryService $query,
        private readonly TelemetryService $telemetry,
    ) {}

    public function index(): View
    {
        return view('dashboard.index', $this->getDashboardData());
    }

    public function data(): JsonResponse
    {
        return response()->json($this->getDashboardData());
    }

    public function session(string $session): View
    {
        $data = $this->query->getSessionDetail($session);
        $data['otherSessions'] = TelemetrySession::where('session_id', '!=', $session)
            ->orderByDesc('last_seen_at')
            ->get();

        $currentSession = $data['session'];
        $data['groupedSessions'] = $currentSession->session_group_id
            ? TelemetrySession::where('session_group_id', $currentSession->session_group_id)
                ->where('session_id', '!=', $session)
                ->orderBy('first_seen_at')
                ->get()
            : collect();

        return view('dashboard.session', $data);
    }

    public function mergeSessions(Request $request, string $session): RedirectResponse
    {
        $request->validate(['merge_into' => 'required|string']);
        $targetId = $request->input('merge_into');

        if ($targetId === $session) {
            return redirect()->route('dashboard.session', $session)->with('error', 'Cannot merge a session into itself.');
        }

        try {
            $this->telemetry->mergeSessions($session, $targetId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return redirect()->route('dashboard.session', $session)->with('error', 'Session not found.');
        }

        return redirect()->route('dashboard.session', $targetId)
            ->with('success', "Session {$session} merged into this session.");
    }

    public function sessionActivity(string $session): JsonResponse
    {
        return response()->json($this->query->getSessionActivity($session));
    }

    public function ungroupSession(string $session): RedirectResponse
    {
        try {
            $this->telemetry->ungroupSession($session);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return redirect()->route('dashboard.session', $session)->with('error', 'Session not found.');
        }

        return redirect()->route('dashboard.session', $session)
            ->with('success', 'Session removed from group.');
    }

    public function groupSessions(Request $request, string $session): RedirectResponse
    {
        $request->validate(['group_with' => 'required|string']);
        $groupWith = $request->input('group_with');

        if ($groupWith === $session) {
            return redirect()->back()->with('error', 'Cannot group a session with itself.');
        }

        try {
            $this->telemetry->groupSessions($session, $groupWith);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return redirect()->back()->with('error', 'Session not found.');
        }

        return redirect()->back()->with('success', 'Sessions grouped together.');
    }

    public function destroySession(string $session): RedirectResponse
    {
        $this->telemetry->deleteSession($session);

        return redirect()->route('dashboard')->with('success', "Session {$session} deleted.");
    }

    public function errors(): View
    {
        return view('dashboard.errors', [
            'errors' => $this->query->getApiErrors(),
        ]);
    }

    public function resetAll(): RedirectResponse
    {
        $this->telemetry->resetAll();

        return redirect()->route('dashboard')->with('success', 'All telemetry data has been reset.');
    }

    private function getDashboardData(): array
    {
        return [
            'summary' => $this->query->getSummary(),
            'sessions' => $this->query->getSessions(),
            'tokenBreakdown' => $this->query->getTokenBreakdown(),
            'locBreakdown' => $this->query->getLinesOfCodeBreakdown(),
            'costByModel' => $this->query->getCostByModel(),
            'toolUsage' => $this->query->getToolUsage(),
            'apiPerformance' => $this->query->getApiPerformance(),
            'recentEvents' => $this->query->getRecentEvents(),
            'billingModel' => config('claude-board.billing_model', 'subscription'),
        ];
    }
}
