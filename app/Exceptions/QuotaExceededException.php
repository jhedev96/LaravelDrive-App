<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised by App\Services\StorageQuota when an upload would put the user
 * over the storage allowance of their billing plan.
 */
class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $remaining = 0,
        public readonly int $requested = 0
    ) {
        parent::__construct(
            sprintf(
                'Storage quota exceeded: %d bytes requested, %d bytes remaining on your plan.',
                $requested,
                $remaining
            )
        );
    }
}
