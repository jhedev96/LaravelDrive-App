<?php

namespace App\Services;

use App\Exceptions\QuotaExceededException;
use App\Plan;
use App\User;

/**
 * Storage quota gate (D18). Inactive while billing.enabled=false so the
 * upgrade-in-place audience keeps unlimited storage until the switch.
 */
class StorageQuota
{
    public function usedFor(User $user): int
    {
        return $user->storageUsed();
    }

    /**
     * Remaining bytes under the user's plan; null = unlimited.
     */
    public function remainingFor(User $user): ?int
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $plan = $user->plan ?: Plan::default();

        if ($plan === null || $plan->max_storage <= 0) {
            // plan missing or "unlimited" configured via 0
            return null;
        }

        return max(0, $plan->max_storage - $this->usedFor($user));
    }

    /**
     * Throws QuotaExceededException when the upload would go over quota.
     */
    public function enforce(User $user, int $incomingBytes): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $remaining = $this->remainingFor($user);

        if ($remaining === null) {
            return;
        }

        if ($incomingBytes > $remaining) {
            throw new QuotaExceededException($remaining, $incomingBytes);
        }
    }

    public function isEnabled(): bool
    {
        return (bool) config('billing.enabled', false);
    }
}
