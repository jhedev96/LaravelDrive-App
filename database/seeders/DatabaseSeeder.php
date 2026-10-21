<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * OAuth clients/keys for the API are NOT seeded here — run
     * `php artisan passport:install` once after migrating.
     */
    public function run(): void
    {
        $this->call([
            PlanTableSeeder::class,
            RoleTableSeeder::class,
            UsersTableSeeder::class,
        ]);
    }
}
