<?php

namespace Database\Seeders;

use App\Plan;
use App\Role;
use App\User;
use Illuminate\Database\Seeder;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::whereName('administrator')->first();

        // demo credentials documented in README (change before deploying)
        $admin = User::firstOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@admin.com'),
        ], [
            'name' => env('ADMIN_NAME', 'admin'),
            'firstname' => 'John',
            'lastname' => 'Doe',
            'password' => env('ADMIN_PASSWORD', 'admin123'), // hashed via model cast
            'email_verified_at' => now(),
            'status' => 1,
            'ip' => '127.0.0.1',
        ]);

        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }

        // the owner account rides the largest plan
        $vip = Plan::query()->where('slug', 'vip')->first();
        if ($vip && ! $admin->plan_id) {
            $admin->plan_id = $vip->id;
            $admin->save();
        }
    }
}
