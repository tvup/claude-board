<?php

namespace Tests\Unit\Services;

use App\Services\VersionService;
use Tests\TestCase;

class VersionServiceTest extends TestCase
{
    private VersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VersionService;
    }

    protected function tearDown(): void
    {
        $versionFile = base_path('.version');
        if (file_exists($versionFile)) {
            unlink($versionFile);
        }
        parent::tearDown();
    }

    public function test_resolve_returns_string(): void
    {
        $version = $this->service->resolve();

        $this->assertIsString($version);
        $this->assertNotEmpty($version);
    }

    public function test_resolve_reads_version_file_when_present(): void
    {
        file_put_contents(base_path('.version'), 'v1.2.3');

        $this->assertSame('v1.2.3', $this->service->resolve());
    }

    public function test_resolve_truncates_full_sha_to_short(): void
    {
        file_put_contents(base_path('.version'), 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2');

        $this->assertSame('a1b2c3d', $this->service->resolve());
    }

    public function test_resolve_ignores_empty_version_file(): void
    {
        file_put_contents(base_path('.version'), '   ');

        $version = $this->service->resolve();

        // Should fall through to git or 'dev', not empty string
        $this->assertNotEmpty($version);
    }

    public function test_resolve_falls_back_to_git_without_version_file(): void
    {
        // No .version file exists, .git dir does exist in test environment
        $version = $this->service->resolve();

        // Should return a git SHA or tag, not 'dev'
        $this->assertNotSame('dev', $version);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{7,}|v\d/', $version);
    }

    public function test_resolve_preserves_non_sha_content(): void
    {
        file_put_contents(base_path('.version'), 'v2.0.0-rc1');

        $this->assertSame('v2.0.0-rc1', $this->service->resolve());
    }
}
