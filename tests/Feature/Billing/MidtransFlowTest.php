<?php

namespace Tests\Feature\Billing;

use App\Plan;
use App\PlanOrder;
use App\Services\Billing\PlanActivator;
use App\User;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MidtransFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.enabled' => true,
            'billing.midtrans.server_key' => 'test-server-key',
            'billing.midtrans.is_production' => false,
        ]);

        Http::preventStrayRequests();

        // plan rows were seeded inside setUp(); give VIP a real price here
        Plan::query()->where('slug', 'vip')->update(['price' => 150000]);
    }

    protected function user(): User
    {
        return User::factory()->create();
    }

    protected function vipPlan(): Plan
    {
        return Plan::query()->where('slug', 'vip')->firstOrFail();
    }

    public function test_plans_catalog_lists_plans_and_usage_meta(): void
    {
        Passport::actingAs($user = $this->user());
        Plan::query()->create(['slug' => 'tiny2', 'name' => 'Tiny2', 'max_storage' => 10, 'active' => false]);

        $response = $this->getJson('/api/billing/plans');

        $response->assertOk();
        $data = $response->json('data');
        // inactive plans are hidden from the catalog
        $this->assertCount(2, array_filter($data, fn ($row) => isset($row['slug'])));
        $this->assertContains('free', array_column(array_filter($data, 'is_array'), 'slug'));
        $this->assertSame(0, $response->json('data.meta.used'));
        // factory users have no plan row yet → free fallback with null reference
        $this->assertNull($response->json('data.meta.plan_id'));
        $this->assertSame('free', $response->json('data.meta.plan_slug'));
    }

    public function test_create_order_returns_snap_token_and_persists_pending_order(): void
    {
        Http::fake([
            'app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-123',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v2/vtgateway/pay',
            ], 200),
        ]);

        Passport::actingAs($this->user());

        $response = $this->postJson('/api/billing/orders', [
            'plan_id' => $this->vipPlan()->id,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.snap_token', 'snap-token-123');

        $order = PlanOrder::query()->latest('id')->first();

        $this->assertSame(PlanOrder::STATUS_PENDING, $order->status);
        $this->assertSame('midtrans', $order->gateway);
        $this->assertSame($this->vipPlan()->price, $order->amount);
        $this->assertSame('snap-token-123', $order->payload['token']);
    }

    public function test_webhook_settlement_activates_vip_plan(): void
    {
        Http::fake(); // no outbound expected

        $user = $this->user();
        $plan = $this->vipPlan();

        $order = PlanOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => PlanOrder::STATUS_PENDING,
            'gateway' => 'midtrans',
        ]);

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'LDORD'.$order->id,
            'status_code' => '200',
            'gross_amount' => (string) $plan->price,
            'transaction_status' => 'settlement',
            'payment_type' => 'qris',
            'transaction_id' => 'midtrans-txn-1',
            'signature_key' => $this->signature('LDORD'.$order->id, '200', (string) $plan->price),
        ]);

        $response->assertNoContent();

        $order->refresh();

        $this->assertSame(PlanOrder::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('midtrans-txn-1', $order->gateway_txn_id);

        $user->refresh();
        $this->assertSame($plan->id, $user->plan_id);
        // 30-day grant with stacking → expiry in the future
        $this->assertTrue($user->plan_expires_at->isFuture());
        $this->assertGreaterThanOrEqual(29, (int) now()->diffInDays($user->plan_expires_at));
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $user = $this->user();
        $plan = $this->vipPlan();

        $order = PlanOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => PlanOrder::STATUS_PENDING,
            'gateway' => 'midtrans',
        ]);

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'LDORD'.$order->id,
            'status_code' => '200',
            'gross_amount' => (string) $plan->price,
            'transaction_status' => 'settlement',
            'signature_key' => 'bogus-signature',
        ]);

        $response->assertStatus(403);

        $order->refresh();
        $user->refresh();
        $this->assertSame(PlanOrder::STATUS_PENDING, $order->status);
        $this->assertNull($user->plan_id);
    }

    public function test_webhook_rejects_amount_mismatches(): void
    {
        $user = $this->user();
        $plan = $this->vipPlan();

        $order = PlanOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => PlanOrder::STATUS_PENDING,
            'gateway' => 'midtrans',
        ]);

        $wrongGross = (string) ($plan->price + 999);

        $response = $this->postJson('/api/webhooks/midtrans', [
            'order_id' => 'LDORD'.$order->id,
            'status_code' => '200',
            'gross_amount' => $wrongGross,
            'transaction_status' => 'settlement',
            'signature_key' => $this->signature('LDORD'.$order->id, '200', $wrongGross),
        ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error', 'amount_mismatch');

        $order->refresh();
        $user->refresh();
        $this->assertSame(PlanOrder::STATUS_PENDING, $order->status);
        $this->assertNull($user->plan_id);
    }

    public function test_webhook_renewal_stacks_expiry(): void
    {
        $user = $this->user();
        $plan = $this->vipPlan();

        $base = now()->addDays(5);
        $user->forceFill(['plan_id' => $plan->id, 'plan_expires_at' => $base])->save();

        $order = PlanOrder::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'status' => PlanOrder::STATUS_PAID,
            'gateway' => 'midtrans',
            'paid_at' => now(),
        ]);

        app(PlanActivator::class)->activate($user, $order);

        $user->refresh();
        // 5 remaining days + 30 = 35
        $this->assertTrue($user->plan_expires_at->gt($base));
        $this->assertGreaterThanOrEqual(34, (int) now()->diffInDays($user->plan_expires_at));
    }

    protected function signature(string $orderId, string $statusCode, string $gross): string
    {
        return hash('sha512', $orderId.$statusCode.$gross.'test-server-key');
    }
}
