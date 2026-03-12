<?php

namespace App\Services;

class VersionService
{
    /**
     * Resolve the application version string.
     *
     * Priority:
     *  1. .version file (baked into Docker image at build time)
     *  2. git describe / git rev-parse (local / Sail dev)
     *  3. "dev" fallback
     */
    public function resolve(): string
    {
        return $this->fromFile() ?? $this->fromGit() ?? 'dev';
    }

    private function fromFile(): ?string
    {
        $path = base_path('.version');

        if (! file_exists($path)) {
            return null;
        }

        $content = trim(file_get_contents($path));

        if ($content === '') {
            return null;
        }

        // Full SHA from Docker build — show short form
        if (preg_match('/^[0-9a-f]{40}$/', $content)) {
            return substr($content, 0, 7);
        }

        return $content;
    }

    private function fromGit(): ?string
    {
        if (! is_dir(base_path('.git'))) {
            return null;
        }

        // Try git describe first (picks up tags like v1.0.0)
        $describe = $this->exec('git describe --tags --always 2>/dev/null');
        if ($describe !== null) {
            return $describe;
        }

        // Fallback to short SHA
        return $this->exec('git rev-parse --short HEAD 2>/dev/null');
    }

    private function exec(string $command): ?string
    {
        $result = trim((string) shell_exec("cd " . escapeshellarg(base_path()) . " && $command"));

        return $result !== '' ? $result : null;
    }
}
