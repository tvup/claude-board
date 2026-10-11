<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class HomeAssistantService
{
    public function getSensorStates(): array
    {
        $baseUrl = rtrim((string) config('claude-board.home_assistant.base_url'), '/');
        $token = (string) config('claude-board.home_assistant.token');
        $definitions = config('claude-board.home_assistant.sensor_definitions', []);

        if ($baseUrl === '' || $token === '' || ! is_array($definitions) || $definitions === []) {
            return [];
        }

        $mapped = [];

        foreach ($definitions as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $entityId = $definition['entity_id'] ?? null;
            if (! is_string($entityId) || $entityId === '') {
                continue;
            }

            try {
                $response = Http::timeout(5)
                    ->withToken($token)
                    ->acceptJson()
                    ->get("{$baseUrl}/api/states/{$entityId}");

                if (! $response->ok()) {
                    continue;
                }

                $payload = $response->json();
                if (! is_array($payload)) {
                    continue;
                }

                $mapped[$key] = [
                    'state' => $payload['state'] ?? null,
                    'attributes' => is_array($payload['attributes'] ?? null) ? $payload['attributes'] : [],
                    'entity_id' => $payload['entity_id'] ?? $entityId,
                ];
            } catch (Throwable) {
                continue;
            }
        }

        return $mapped;
    }
}
