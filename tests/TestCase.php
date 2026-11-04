<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected string $testSettingsStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        // Never let the test suite read or write the real settings/logo files.
        $this->testSettingsStoragePath = storage_path('framework/testing/bar-settings-'.getmypid().'-'.uniqid());
        config([
            'settings.file' => $this->testSettingsStoragePath.'/bar.json',
            'settings.branding_directory' => $this->testSettingsStoragePath.'/branding',
        ]);
        File::ensureDirectoryExists($this->testSettingsStoragePath.'/branding', 0775, true);
    }

    protected function tearDown(): void
    {
        if (isset($this->testSettingsStoragePath)) {
            File::deleteDirectory($this->testSettingsStoragePath);
        }

        parent::tearDown();
    }
}
