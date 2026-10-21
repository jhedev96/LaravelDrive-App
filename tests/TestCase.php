<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    // Database\Seeders\DatabaseSeeder runs after every RefreshDatabase run
    protected bool $seed = true;

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
