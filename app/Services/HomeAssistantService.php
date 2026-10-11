<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class HomeAssistantService
{
    /**
     * Fetch all HA states in one request and map them to the configured sensor keys.
     *
     * An entity matches its definition either exactly, or when HA has prefixed the
     * object ID (e.g. a device name), as in `sensor.<prefix>_claude_usage_plan`.
     *
     * @return array<string, array{state: mixed, attributes: array<string, mixed>, entity_id: string}>
     */
    public function getSensorStates(): array
    {
        $baseUrl = rtrim((string) config('claude-board.home_assistant.base_url'), '/');
        $token = (string) config('claude-board.home_assistant.token');
        $definitions = config('claude-board.home_assistant.sensor_definitions', []);

        if ($baseUrl === '' || $token === '' || ! is_array($definitions) || $definitions === []) {
            return [];
        }

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->acceptJson()
                ->get("{$baseUrl}/api/states");
        } catch (Throwable) {
            return [];
        }

        $states = $response->ok() ? $response->json() : null;

        if (! is_array($states)) {
            return [];
        }

        $byEntityId = [];

        foreach ($states as $state) {
            if (is_array($state) && is_string($state['entity_id'] ?? null)) {
                $byEntityId[$state['entity_id']] = $state;
            }
        }

        $mapped = [];

        foreach ($definitions as $key => $definition) {
            $entityId = is_array($definition) ? ($definition['entity_id'] ?? null) : null;

            if (! is_string($entityId) || $entityId === '') {
                continue;
            }

            $state = $byEntityId[$entityId] ?? $this->findPrefixedEntity($byEntityId, $entityId);

            if ($state === null) {
                continue;
            }

            $mapped[$key] = [
                'state' => $state['state'] ?? null,
                'attributes' => is_array($state['attributes'] ?? null) ? $state['attributes'] : [],
                'entity_id' => $state['entity_id'],
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, array<string, mixed>>  $byEntityId
     * @return array<string, mixed>|null
     */
    private function findPrefixedEntity(array $byEntityId, string $entityId): ?array
    {
        [$domain, $objectId] = array_pad(explode('.', $entityId, 2), 2, '');

        foreach ($byEntityId as $candidateId => $state) {
            if (str_starts_with($candidateId, "{$domain}.") && str_ends_with($candidateId, "_{$objectId}")) {
                return $state;
            }
        }

        return null;
    }
}
