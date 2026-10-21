<?php

namespace App\Services\Billing;

use App\PlanOrder;
use App\User;
use Illuminate\Support\Facades\DB;

/**
 * Grants a paid plan to the user once an order reaches the paid state.
 * Stacking is allowed: renewals extend the current expiry instead of
 * restarting the clock (QRIS one-time model, D18).
 */
class PlanActivator
{
    public const DEFAULT_DURATION_DAYS = 30;

    public function activate(User $user, PlanOrder $order): void
    {
        if ($order->status !== PlanOrder::STATUS_PAID) {
            return;
        }

        $durationDays = (int) ($order->plan->duration_days ?? self::DEFAULT_DURATION_DAYS);

        DB::transaction(function () use ($user, $order, $durationDays): void {
            // restart the clock only when the current grant has lapsed
            $base = $user->plan_expires_at && $user->plan_expires_at->isFuture()
                ? $user->plan_expires_at
                : now();

            $user->forceFill([
                'plan_id' => $order->plan_id,
                'plan_expires_at' => $base->copy()->addDays($durationDays),
            ])->save();
        });
    }
}
