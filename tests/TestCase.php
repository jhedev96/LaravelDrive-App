<?php

namespace Tests;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // Database\Seeders\DatabaseSeeder runs after every RefreshDatabase run
    protected bool $seed = true;

    /**
     * The database resets per test (RefreshDatabase); storage does not.
     * Sweep the two writable app disks after every test so uploads/zips
     * never leak between runs (the box accumulated ~150 junk dirs once).
     */
    protected function tearDown(): void
    {
        $this->cleanStorageDirectory(storage_path('app/uploads'));
        $this->cleanStorageDirectory(storage_path('app/public'));

        parent::tearDown();
    }

    protected function cleanStorageDirectory(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }

        foreach (new \FilesystemIterator($root, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isDir()) {
                (new Filesystem)->deleteDirectory($entry->getPathname());

                continue;
            }

            // keep the repo-placed .gitignore in place
            if ($entry->getFilename() !== '.gitignore') {
                @unlink($entry->getPathname());
            }
        }
    }

    /**
     * Passport 13 keeps no personal access client unless one is created;
     * feature tests that call createToken() need it in the test database.
     */
    protected function ensurePersonalAccessClient(): void
    {
        $this->artisan('passport:client', [
            '--personal' => true,
            '--name' => 'Testing Personal Access Client',
            '--provider' => 'users',
        ]);
    }
}
