<?php

namespace App\Http\Controllers;

use App\Services\DashboardQueryService;
use App\Services\HomeAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardQueryService $dashboardQueryService,
        private readonly HomeAssistantService $homeAssistantService,
    ) {}

    public function index(): View
    {
        $summary = $this->dashboardQueryService->getSummary();

        return view('dashboard.index', [
            'summary' => $summary,
            'tokenBreakdown' => [
                'input' => 0,
                'output' => 0,
            ],
            'locBreakdown' => [
                'added' => 0,
                'removed' => 0,
            ],
            'billingModel' => 'subscription',
            'claudeUsage' => $this->resolveClaudeUsage(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'summary' => $this->dashboardQueryService->getSummary(),
            'claude_usage' => $this->resolveClaudeUsage(),
        ]);
    }

    public function errors(): View
    {
        return view('dashboard.errors', [
            'errors' => $this->dashboardQueryService->getApiErrors(),
        ]);
    }

    public function connectivityErrors(): View
    {
        return view('dashboard.connectivity-errors', [
            'errors' => $this->dashboardQueryService->getConnectivityErrors(),
        ]);
    }

    public function logConnectivityError(Request $request): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function clearConnectivityErrors(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function session(string $session): View
    {
        $detail = $this->dashboardQueryService->getSessionDetail($session);

        return view('dashboard.session', $detail);
    }

    public function destroySession(string $session): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function mergeSessions(string $session): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function ungroupSession(string $session): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function groupSessions(string $session): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function sessionActivity(string $session): JsonResponse
    {
        return response()->json($this->dashboardQueryService->getSessionActivity($session));
    }

    public function resetAll(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    private function resolveClaudeUsage(): ?array
    {
        $states = $this->homeAssistantService->getSensorStates();


        $value = static fn (string $key): mixed => $states[$key]['state'] ?? null;

        return [
            'subscription_type' => $value('subscription_type'),
            'five_hour_utilization' => is_numeric($value('current_session_percent')) ? (float) $value('current_session_percent') : 0.0,
            'seven_day_utilization' => is_numeric($value('this_week_percent')) ? (float) $value('this_week_percent') : 0.0,
            'seven_day_sonnet_utilization' => 0.0,
            'limit_resets_count' => is_numeric($value('limit_resets_count')) ? (int) $value('limit_resets_count') : 0,
            'cloud_credits_left' => $value('cloud_session_credits_left'),
            'cloud_credits_total' => $value('cloud_session_credits_total'),
            'cloud_credits_expires_at' => $value('cloud_session_credits_expires_at'),
            'usage_credits' => $value('usage_credits_amount'),
            'usage_credits_enabled' => filter_var($value('usage_credits_enabled'), FILTER_VALIDATE_BOOL),
            'usage_credits_info' => $value('usage_credits_info'),
            'snapshot_at' => now()->toIso8601String(),
        ];
    }
}
