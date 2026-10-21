<?php

namespace Database\Seeders;

use App\Role;
use Illuminate\Database\Seeder;

class RoleTableSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate([
            'name' => 'administrator',
        ], [
            'description' => 'Role to manage all content.',
            'permissions' => ['administrator'],
            'status' => 1,
        ]);
    }
}
