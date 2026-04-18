<?php

namespace App\Http\Controllers;

use App\Models\ConnectivityError;
use App\Models\TelemetrySession;
use App\Services\DashboardQueryService;
use App\Services\TelemetryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        } catch (ModelNotFoundException) {
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
        } catch (ModelNotFoundException) {
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
        } catch (ModelNotFoundException) {
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

    public function connectivityErrors(): View
    {
        return view('dashboard.connectivity-errors', [
            'errors' => $this->query->getConnectivityErrors(),
        ]);
    }

    public function logConnectivityError(Request $request): JsonResponse
    {
        ConnectivityError::create([
            'http_status' => $request->integer('http_status') ?: null,
            'endpoint'    => '/api/dashboard-data',
            'created_at'  => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function updateProject(Request $request, string $session): JsonResponse
    {
        $validated = $request->validate([
            'project_name' => 'required|string|max:255',
            'hostname' => 'nullable|string|max:255',
        ]);

        $telemetrySession = TelemetrySession::where('session_id', $session)->first();

        if ($telemetrySession) {
            $update = [];
            if (! $telemetrySession->project_name || $telemetrySession->project_name === 'background') {
                $update['project_name'] = $validated['project_name'];
            }
            if (! empty($validated['hostname']) && ! $telemetrySession->hostname) {
                $update['hostname'] = $validated['hostname'];
            }
            if ($update) {
                $telemetrySession->update($update);
            }
        } else {
            $pending = ['project_name' => $validated['project_name']];
            if (! empty($validated['hostname'])) {
                $pending['hostname'] = $validated['hostname'];
            }
            cache()->put("pending_session_meta:{$session}", $pending, 300);
        }

        return response()->json(['ok' => true]);
    }

    public function resetAll(): RedirectResponse
    {
        $this->telemetry->resetAll();

        return redirect()->route('dashboard')->with('success', 'All telemetry data has been reset.');
    }

    private function getDashboardData(): array
    {
        $ttl = config('claude-board.dashboard_cache_ttl', 5);

        return cache()->remember('dashboard_data', $ttl, function () {
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
                'claudeUsage' => $this->fetchClaudeUsage(),
            ];
        });
    }

    private function fetchClaudeUsage(): ?array
    {
        $url = config('claude-board.usage_api_url');

        if (empty($url)) {
            return null;
        }

        $ttl = config('claude-board.usage_api_cache_ttl', 20);

        return cache()->remember('claude_usage_data', $ttl, function () use ($url) {
            try {
                $response = Http::timeout(3)->get($url);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable) {
                // Connection error, timeout, etc. — silently ignore
            }

            return null;
        });
    }
}
