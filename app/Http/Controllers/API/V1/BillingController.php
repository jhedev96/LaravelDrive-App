<?php

namespace App\Http\Controllers\API\V1;

use App\Plan;
use App\PlanOrder;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\StorageQuota;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Billing API (D18): plans catalog + QRIS order creation.
 */
class BillingController extends ApiController
{
    /**
     * Plans catalog + my current usage for the plan selector UI.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $plans = Plan::query()->where('active', true)->orderBy('price')->get();

        return new JsonResource($plans->map(fn (Plan $plan) => [
            'id' => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'description' => $plan->description,
            'max_storage' => $plan->max_storage,
            'price' => $plan->price,
            'currency' => $plan->currency,
            'duration_days' => $plan->duration_days,
            'active' => $plan->active,
        ])->all() + [
            'meta' => [
                'enabled' => app(StorageQuota::class)->isEnabled(),
                'used' => $user->storageUsed(),
                'remaining' => app(StorageQuota::class)->remainingFor($user),
                'plan_id' => $user->plan_id,
                'plan_slug' => $user->plan?->slug ?: config('billing.default_plan_slug'),
                'plan_expires_at' => $user->plan_expires_at,
            ],
        ]);
    }

    /**
     * Purchase attempt for a plan — returns the Snap payment token.
     */
    public function store(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', Rule::exists('plans', 'id')->where('active', true)],
        ]);

        $plan = Plan::query()->findOrFail($request->input('plan_id'));

        if ((int) $plan->price <= 0) {
            // the free plan needs no order
            return $this->errorForbidden('The free plan cannot be purchased.');
        }

        $order = PlanOrder::query()->create([
            'user_id' => $request->user()->id,
            'plan_id' => $plan->id,
            'amount' => $plan->price,
            'currency' => $plan->currency,
            'status' => PlanOrder::STATUS_PENDING,
            'gateway' => config('billing.gateway'),
        ]);

        $gateway = $this->gateway();

        $token = DB::transaction(function () use ($order, $gateway) {
            $transaction = $gateway->createTransaction($order);

            $order->payload = $transaction;
            $order->save();

            return $transaction;
        });

        return new JsonResource(array_merge($order->load('plan')->toArray(), [
            'snap_token' => $token['token'],
            'snap_url' => $token['url'],
        ]));
    }

    /**
     * History of my own orders.
     */
    public function orders(Request $request)
    {
        $orders = PlanOrder::query()
            ->with('plan')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return new JsonResource($orders);
    }

    protected function gateway(): PaymentGateway
    {
        // rail bound in AppServiceProvider (midtrans primary / xendit later)
        return app(PaymentGateway::class);
    }
}
