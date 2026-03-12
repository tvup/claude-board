<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class DevSimulate extends Command
{
    protected $signature = 'dev:simulate
        {--sessions=2 : Number of concurrent sessions}
        {--speed=1.0 : Speed multiplier (2.0 = double speed)}
        {--endpoint= : OTLP endpoint URL (auto-detects Sail vs local)}
        {--projects=* : Project names (default: random selection)}
        {--duration=120 : Simulation duration in seconds (0 = infinite)}';

    protected $description = 'Simulate fake Claude Code telemetry for local development';

    private const MODELS = [
        ['name' => 'claude-sonnet-4-5-20250514', 'weight' => 70, 'cost_range' => [0.002, 0.015]],
        ['name' => 'claude-opus-4-5-20250514', 'weight' => 20, 'cost_range' => [0.008, 0.045]],
        ['name' => 'claude-haiku-3-5-20241022', 'weight' => 10, 'cost_range' => [0.0005, 0.003]],
    ];

    private const TOOLS = [
        ['name' => 'Read', 'success_rate' => 98, 'duration' => [50, 300]],
        ['name' => 'Write', 'success_rate' => 95, 'duration' => [100, 500]],
        ['name' => 'Edit', 'success_rate' => 92, 'duration' => [100, 600]],
        ['name' => 'Bash', 'success_rate' => 88, 'duration' => [200, 5000]],
        ['name' => 'Glob', 'success_rate' => 99, 'duration' => [30, 150]],
        ['name' => 'Grep', 'success_rate' => 97, 'duration' => [50, 400]],
        ['name' => 'WebFetch', 'success_rate' => 85, 'duration' => [500, 3000]],
        ['name' => 'Agent', 'success_rate' => 90, 'duration' => [1000, 8000]],
    ];

    private const DEFAULT_PROJECTS = ['claude-board', 'api-gateway', 'frontend-app', 'data-pipeline', 'auth-service'];

    private const TERMINALS = ['vscode', 'iterm2', 'terminal', 'warp', 'cursor'];

    private const ERROR_MESSAGES = [
        'Rate limit exceeded',
        'Internal server error',
        'Request timeout',
        'Model overloaded',
        'Invalid request format',
    ];

    private bool $running = true;

    private int $totalMetricsSent = 0;

    private int $totalEventsSent = 0;

    private int $totalErrors = 0;

    public function handle(): int
    {
        $sessionCount = (int) $this->option('sessions');
        $speed = (float) $this->option('speed');
        $endpoint = $this->option('endpoint') ?: $this->resolveEndpoint();
        $endpoint = rtrim($endpoint, '/');
        $duration = (int) $this->option('duration');
        $projects = $this->option('projects') ?: self::DEFAULT_PROJECTS;

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () {
                $this->running = false;
            });
        }

        $this->printHeader($endpoint, $sessionCount, $speed, $projects);

        // Initialize sessions
        $sessions = [];
        for ($i = 0; $i < $sessionCount; $i++) {
            $sessions[] = $this->createSession($projects);
        }

        $startTime = time();
        $stepIndex = 0;

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            if ($duration > 0 && (time() - $startTime) >= $duration) {
                break;
            }

            // Round-robin through sessions
            $session = &$sessions[$stepIndex % count($sessions)];

            $this->stepSession($session, $endpoint);

            // Check if session is complete
            if ($session['state'] === 'done') {
                // 50% chance to continue as new grouped session
                if (rand(1, 100) <= 50) {
                    $oldProject = $session['project'];
                    $oldUser = $session['user_email'];
                    $oldUserId = $session['user_id'];
                    $session = $this->createSession([$oldProject]);
                    $session['user_email'] = $oldUser;
                    $session['user_id'] = $oldUserId;
                    $this->line("  <fg=cyan>  ↳ Continued session (grouping test)</>");
                } else {
                    $session = $this->createSession($projects);
                }
            }

            $stepIndex++;

            $delay = (int) ((2_000_000 / $speed));
            usleep($delay);
        }

        $this->newLine();
        $this->printStats($startTime);

        return self::SUCCESS;
    }

    private function createSession(array $projects): array
    {
        $totalTurns = rand(2, 5);

        return [
            'session_id' => (string) Str::ulid(),
            'project' => $projects[array_rand($projects)],
            'user_email' => 'dev@cipher.local',
            'user_id' => 'user-sim-'.rand(10, 99),
            'terminal' => self::TERMINALS[array_rand(self::TERMINALS)],
            'state' => 'user_prompt',
            'turn' => 0,
            'total_turns' => $totalTurns,
            'tool_count' => 0,
            'max_tools' => rand(0, 4),
            'active_time' => 0,
            'wait_steps' => 0,
        ];
    }

    private function stepSession(array &$session, string $endpoint): void
    {
        // Handle wait steps (simulates user think time between turns)
        if ($session['wait_steps'] > 0) {
            $session['wait_steps']--;

            return;
        }

        $now = now();
        $timeNano = (string) ($now->timestamp * 1_000_000_000);
        $sessionAttrs = $this->sessionAttributes($session);

        switch ($session['state']) {
            case 'user_prompt':
                $this->sendLog($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'user_prompt', []);
                $this->printEvent($now, $session, 'user_prompt', $session['project']);
                $session['state'] = 'api_request';
                $session['tool_count'] = 0;
                $session['max_tools'] = rand(0, 8);
                break;

            case 'api_request':
                $model = $this->pickModel();
                $durationMs = rand(800, 4000);
                $cost = $this->randomFloat($model['cost_range'][0], $model['cost_range'][1]);
                $inputTokens = rand(500, 8000);
                $outputTokens = rand(100, 4000);
                $cacheRead = rand(0, 3000);
                $cacheCreation = rand(0, 500);

                // 3-5% error rate
                $isError = rand(1, 100) <= rand(3, 5);

                if ($isError) {
                    $error = self::ERROR_MESSAGES[array_rand(self::ERROR_MESSAGES)];
                    $this->sendLog($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'api_error', [
                        'error' => $error,
                        'model' => $model['name'],
                    ]);
                    $this->printEvent($now, $session, 'api_error', "<fg=red>{$error}</>");
                    $this->totalErrors++;
                    // Retry — stay in api_request state
                    break;
                }

                // Send api_request event
                $this->sendLog($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'api_request', [
                    'model' => $model['name'],
                    'duration_ms' => (string) $durationMs,
                    'cost_usd' => (string) round($cost, 6),
                    'input_tokens' => (string) $inputTokens,
                    'output_tokens' => (string) $outputTokens,
                    'cache_read_tokens' => (string) $cacheRead,
                    'cache_creation_tokens' => (string) $cacheCreation,
                ]);

                // Send cost metric
                $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.cost.usage', 'USD', $cost);

                // Send token metrics
                $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.token.usage', 'tokens', $inputTokens, ['type' => 'input']);
                $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.token.usage', 'tokens', $outputTokens, ['type' => 'output']);
                $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.token.usage', 'tokens', $cacheRead, ['type' => 'cacheRead']);
                $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.token.usage', 'tokens', $cacheCreation, ['type' => 'cacheCreation']);

                // Send active time
                $activeIncrement = rand(10, 30);
                $session['active_time'] += $activeIncrement;
                $this->sendGaugeMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.active_time.total', 's', $session['active_time']);

                $shortModel = Str::afterLast($model['name'], '-20');
                $shortModel = Str::before($model['name'], '-20');
                $this->printEvent($now, $session, 'api_request', "{$shortModel} | \$".number_format($cost, 4)." | {$durationMs}ms");

                // Decide next state: tool or next turn
                if ($session['tool_count'] < $session['max_tools'] && rand(1, 100) <= 70) {
                    $session['state'] = 'tool_decision';
                } else {
                    $session['turn']++;
                    if ($session['turn'] >= $session['total_turns']) {
                        $session['state'] = 'done';
                    } else {
                        $session['state'] = 'user_prompt';
                        $session['wait_steps'] = rand(2, 3);
                    }
                }
                break;

            case 'tool_decision':
                $tool = self::TOOLS[array_rand(self::TOOLS)];
                $session['current_tool'] = $tool;

                $this->sendLog($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'tool_decision', [
                    'tool_name' => $tool['name'],
                ]);
                $this->printEvent($now, $session, 'tool_decision', $tool['name']);
                $session['state'] = 'tool_result';
                break;

            case 'tool_result':
                $tool = $session['current_tool'];
                $success = rand(1, 100) <= $tool['success_rate'];
                $durationMs = rand($tool['duration'][0], $tool['duration'][1]);

                $this->sendLog($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'tool_result', [
                    'tool_name' => $tool['name'],
                    'success' => $success ? 'true' : 'false',
                    'duration_ms' => (string) $durationMs,
                ]);

                $statusText = $success ? '<fg=green>OK</>' : '<fg=red>FAIL</>';
                $this->printEvent($now, $session, 'tool_result', "{$tool['name']} | {$statusText} | {$durationMs}ms");

                // Lines of code for write/edit tools
                if (in_array($tool['name'], ['Write', 'Edit']) && $success) {
                    $added = rand(1, 50);
                    $removed = rand(0, 20);
                    $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.lines_of_code.count', 'lines', $added, ['type' => 'added']);
                    $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.lines_of_code.count', 'lines', $removed, ['type' => 'removed']);
                }

                // Commit chance (5% per turn)
                if (rand(1, 100) <= 5) {
                    $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.commit.count', 'commits', 1);
                }

                // PR chance (2% per turn)
                if (rand(1, 100) <= 2) {
                    $this->sendMetric($endpoint, $session['session_id'], $sessionAttrs, $timeNano, 'claude_code.pull_request.count', 'prs', 1);
                }

                $session['tool_count']++;

                // Back to api_request for another tool cycle or next turn
                $session['state'] = 'api_request';
                break;
        }
    }

    private function sendLog(string $endpoint, string $sessionId, array $sessionAttrs, string $timeNano, string $eventName, array $extraAttrs): void
    {
        $attributes = array_merge($sessionAttrs, [
            ['key' => 'event.name', 'value' => ['stringValue' => $eventName]],
        ]);

        foreach ($extraAttrs as $key => $val) {
            $attributes[] = ['key' => $key, 'value' => ['stringValue' => $val]];
        }

        $payload = [
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
                                    'timeUnixNano' => $timeNano,
                                    'severityText' => $eventName === 'api_error' ? 'ERROR' : 'INFO',
                                    'body' => ['stringValue' => $eventName],
                                    'attributes' => $attributes,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            Http::timeout(5)->post("{$endpoint}/v1/logs", $payload);
            $this->totalEventsSent++;
        } catch (\Throwable $e) {
            $this->line("  <fg=red>HTTP error: {$e->getMessage()}</>");
        }
    }

    private function sendMetric(string $endpoint, string $sessionId, array $sessionAttrs, string $timeNano, string $name, string $unit, float $value, array $extraAttrs = []): void
    {
        $attributes = $sessionAttrs;
        foreach ($extraAttrs as $key => $val) {
            $attributes[] = ['key' => $key, 'value' => ['stringValue' => $val]];
        }

        $payload = [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'claude-code']],
                        ],
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'metrics' => [
                                [
                                    'name' => $name,
                                    'unit' => $unit,
                                    'sum' => [
                                        'dataPoints' => [
                                            [
                                                'asDouble' => $value,
                                                'timeUnixNano' => $timeNano,
                                                'attributes' => $attributes,
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

        try {
            Http::timeout(5)->post("{$endpoint}/v1/metrics", $payload);
            $this->totalMetricsSent++;
        } catch (\Throwable $e) {
            // Silently skip — log errors are shown in sendLog
        }
    }

    private function sendGaugeMetric(string $endpoint, string $sessionId, array $sessionAttrs, string $timeNano, string $name, string $unit, float $value): void
    {
        $payload = [
            'resourceMetrics' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'claude-code']],
                        ],
                    ],
                    'scopeMetrics' => [
                        [
                            'scope' => ['name' => 'claude-code'],
                            'metrics' => [
                                [
                                    'name' => $name,
                                    'unit' => $unit,
                                    'gauge' => [
                                        'dataPoints' => [
                                            [
                                                'asInt' => (int) $value,
                                                'timeUnixNano' => $timeNano,
                                                'attributes' => $sessionAttrs,
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

        try {
            Http::timeout(5)->post("{$endpoint}/v1/metrics", $payload);
            $this->totalMetricsSent++;
        } catch (\Throwable $e) {
            // Silently skip
        }
    }

    private function sessionAttributes(array $session): array
    {
        return [
            ['key' => 'session.id', 'value' => ['stringValue' => $session['session_id']]],
            ['key' => 'user.email', 'value' => ['stringValue' => $session['user_email']]],
            ['key' => 'user.id', 'value' => ['stringValue' => $session['user_id']]],
            ['key' => 'terminal.type', 'value' => ['stringValue' => $session['terminal']]],
            ['key' => 'project.name', 'value' => ['stringValue' => $session['project']]],
            ['key' => 'billing.model', 'value' => ['stringValue' => 'subscription']],
        ];
    }

    private function resolveEndpoint(): string
    {
        if (env('LARAVEL_SAIL')) {
            return 'http://laravel.test';
        }

        return 'http://localhost:'.(env('APP_PORT', 8080));
    }

    private function pickModel(): array
    {
        $roll = rand(1, 100);
        $cumulative = 0;
        foreach (self::MODELS as $model) {
            $cumulative += $model['weight'];
            if ($roll <= $cumulative) {
                return $model;
            }
        }

        return self::MODELS[0];
    }

    private function randomFloat(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }

    private function printHeader(string $endpoint, int $sessions, float $speed, array $projects): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold> \u{27e6} DEV SIMULATOR \u{27e7}</> Target: {$endpoint}");
        $this->line(" \u{251c}\u{2500} Sessions: {$sessions} | Speed: {$speed}x");
        $this->line(" \u{2514}\u{2500} Projects: ".implode(', ', $projects));
        $this->newLine();
    }

    private function printEvent(\Carbon\Carbon $time, array $session, string $event, string $details = ''): void
    {
        $timestamp = $time->format('H:i:s');
        $shortId = substr($session['session_id'], 0, 10).'...';

        $coloredEvent = match ($event) {
            'user_prompt' => "<fg=yellow>{$event}</>",
            'api_request' => "<fg=blue>{$event}</>",
            'api_error' => "<fg=red>{$event}</>",
            'tool_decision' => "<fg=magenta>{$event}</>",
            'tool_result' => "<fg=green>{$event}</>",
            default => $event,
        };

        $paddedEvent = str_pad(strip_tags($coloredEvent), 15);
        // Re-apply colors after padding
        $paddedEvent = match ($event) {
            'user_prompt' => "<fg=yellow>".str_pad($event, 15)."</>",
            'api_request' => "<fg=blue>".str_pad($event, 15)."</>",
            'api_error' => "<fg=red>".str_pad($event, 15)."</>",
            'tool_decision' => "<fg=magenta>".str_pad($event, 15)."</>",
            'tool_result' => "<fg=green>".str_pad($event, 15)."</>",
            default => str_pad($event, 15),
        };

        $detailStr = $details ? " \u{2502} {$details}" : '';
        $this->line(" <fg=gray>[{$timestamp}]</> {$shortId} \u{2502} {$paddedEvent}{$detailStr}");
    }

    private function printStats(int $startTime): void
    {
        $elapsed = time() - $startTime;
        $this->newLine();
        $this->line('<fg=cyan;options=bold> ⟦ SIMULATION COMPLETE ⟧</>');
        $this->line(" \u{251c}\u{2500} Duration: {$elapsed}s");
        $this->line(" \u{251c}\u{2500} Events sent: {$this->totalEventsSent}");
        $this->line(" \u{251c}\u{2500} Metrics sent: {$this->totalMetricsSent}");
        $this->line(" \u{2514}\u{2500} Simulated errors: {$this->totalErrors}");
        $this->newLine();
    }
}
