<?php

namespace Tests\Feature\Billing;

use App\Plan;
use App\Services\StorageQuota;
use App\User;
use App\Exceptions\QuotaExceededException;
use App\File;
use Tests\TestCase;

class StorageQuotaTest extends TestCase
{
    public function test_plans_are_seeded_with_free_and_vip(): void
    {
        $slugs = Plan::query()->pluck('slug')->all();

        $this->assertContains('free', $slugs);
        $this->assertContains('vip', $slugs);
        $this->assertTrue(Plan::query()->where('slug', 'free')->first()->price === 0);
    }

    public function test_new_users_start_on_the_free_plan(): void
    {
        $this->ensurePersonalAccessClient();

        $response = $this->post('/register', [
            'name' => 'Quota Tester',
            'email' => 'quota@test.local',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSuccessful();

        $user = User::query()->where('email', 'quota@test.local')->firstOrFail();

        $this->assertSame(Plan::default()->id, $user->plan_id);
        $this->assertArrayHasKey('token', $response->json());
    }

    public function test_storage_used_sums_all_file_sizes_for_the_user(): void
    {
        $user = User::factory()->create();

        File::factory()->create(['uploaded_by' => $user->id, 'file_size' => 300]);
        File::factory()->create(['uploaded_by' => $user->id, 'file_size' => 1200]);
        // other user's files must not count
        File::factory()->create(['file_size' => 9999]);

        $this->assertSame(1500, $user->storageUsed());
    }

    public function test_quota_wide_open_while_billing_is_disabled(): void
    {
        config(['billing.enabled' => false]);

        $user = User::factory()->create();

        // service should not throw regardless of size
        app(StorageQuota::class)->enforce($user, PHP_INT_MAX / 2);

        $this->assertNull(app(StorageQuota::class)->remainingFor($user));
    }

    public function test_quota_enforced_when_billing_enabled(): void
    {
        config(['billing.enabled' => true]);

        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'slug' => 'tiny',
            'name' => 'Tiny',
            'max_storage' => 1000,
        ]);
        $user->forceFill(['plan_id' => $plan->id])->save();

        File::factory()->create(['uploaded_by' => $user->id, 'file_size' => 600]);

        $service = app(StorageQuota::class);

        // 400 bytes left, so this fits
        $service->enforce($user, 400);

        // ...but this one exceeds it
        try {
            $service->enforce($user, 401);
            $this->fail('QuotaExceededException was not thrown.');
        } catch (QuotaExceededException $e) {
            $this->assertSame(400, $e->remaining);
            $this->assertSame(401, $e->requested);
        }

        $this->assertSame(400, $service->remainingFor($user));
    }

    public function test_quota_unlimited_when_plan_max_is_zero(): void
    {
        config(['billing.enabled' => true]);

        $user = User::factory()->create();
        $plan = Plan::query()->create([
            'slug' => 'infinity',
            'name' => 'Infinity',
            'max_storage' => 0,
        ]);
        $user->forceFill(['plan_id' => $plan->id])->save();

        app(StorageQuota::class)->enforce($user, PHP_INT_MAX / 2);

        $this->assertNull(app(StorageQuota::class)->remainingFor($user));
    }
}
