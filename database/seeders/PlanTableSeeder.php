<?php

namespace Database\Seeders;

use App\Plan;
use Illuminate\Database\Seeder;

class PlanTableSeeder extends Seeder
{
    public function run(): void
    {
        $free = (array) config('billing.free');

        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => $free['name'] ?? 'Free',
            'description' => 'Paket dasar dengan kuota penyimpanan terbatas.',
            'max_storage' => $free['max_storage'] ?? 0,
            'price' => 0,
            'currency' => 'IDR',
            'active' => true,
        ]);

        $vip = (array) config('billing.vip');

        Plan::updateOrCreate(['slug' => 'vip'], [
            'name' => $vip['name'] ?? 'VIP',
            'description' => 'Kuota penyimpanan diperbesar (premium).',
            'max_storage' => $vip['max_storage'] ?? 0,
            'price' => $vip['price'] ?? 0,
            'currency' => $vip['currency'] ?? 'IDR',
            'duration_days' => $vip['duration_days'] ?? null,
            'active' => true,
        ]);
    }
}
