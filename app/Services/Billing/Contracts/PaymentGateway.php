<?php

namespace App\Services\Billing\Contracts;

use App\PlanOrder;

/**
 * Payment gateway abstraction (D18). Midtrans is the primary rail;
 * Xendit fallback swaps in behind this contract.
 */
interface PaymentGateway
{
    /**
     * Create a gateway transaction (e.g. Snap token for QRIS) for the
     * pending order.
     *
     * @return array{token: string, url: string, gateway_txn_id?: string}
     */
    public function createTransaction(PlanOrder $order): array;

    /**
     * Validate the gateway signature of an inbound notification and
     * normalize it into our contract.
     *
     * @return array{order_ref: string, status: string, paid: bool, transaction_id: ?string, amount: int}
     */
    public function parseNotification(array $payload): array;
}
