<?php

namespace App\Services\Billing;

use App\PlanOrder;
use App\Services\Billing\Contracts\PaymentGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Midtrans Snap QRIS integration.
 *
 * Docs: https://docs.midtrans.com (Snap API + HTTP notifications).
 * Signatures: sha512(order_id + status_code + gross_amount + server_key).
 */
class MidtransGateway implements PaymentGateway
{
    /**
     * Prefix used so notifications can be mapped back to our orders.
     */
    public const ORDER_PREFIX = 'LDORD';

    public function createTransaction(PlanOrder $order): array
    {
        $serverKey = (string) config('billing.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('Midtrans server key is not configured.');
        }

        // Snap is one-shot per order; retries create fresh tokens for the
        // same order_id (Midtrans keeps the order stable on its side).
        $response = Http::asJson()
            ->withBasicAuth($serverKey, '')
            ->post($this->snapUrl(), [
                'transaction_details' => [
                    'order_id' => $this->orderRef($order),
                    'gross_amount' => (int) $order->plan->price,
                ],
                'item_details' => [
                    [
                        'id' => (string) $order->plan->id,
                        'price' => (int) $order->plan->price,
                        'quantity' => 1,
                        'name' => 'Storage: '.$order->plan->name,
                        'category' => 'storage',
                    ],
                ],
                'customer_details' => [
                    'first_name' => $order->user->display_name,
                    'email' => $order->user->email,
                ],
                'enabled_payments' => ['qris'],
                'expiry' => [
                    'unit' => 'day',
                    'duration' => 1,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Midtrans Snap request failed: '.$response->json('error_messages.0', $response->status())
            );
        }

        return [
            'token' => (string) $response->json('token'),
            'url' => (string) $response->json('redirect_url'),
        ];
    }

    public function parseNotification(array $payload): array
    {
        $serverKey = (string) config('billing.midtrans.server_key');

        $expected = hash('sha512', implode('', [
            $payload['order_id'] ?? '',
            $payload['status_code'] ?? '',
            $payload['gross_amount'] ?? '',
            $serverKey,
        ]));

        $signature = (string) ($payload['signature_key'] ?? '');

        if ($signature === '' || ! hash_equals($expected, $signature)) {
            throw new RuntimeException('Midtrans signature verification failed.');
        }

        $orderRef = (string) $payload['order_id'];

        // our order id is the trailing number after the prefix
        if (! str_starts_with($orderRef, self::ORDER_PREFIX)) {
            throw new RuntimeException('Unrecognized Midtrans order id.');
        }

        $transactionStatus = (string) ($payload['transaction_status'] ?? '');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');

        $paid = $transactionStatus === 'settlement'
            || ($transactionStatus === 'capture' && in_array($fraudStatus, ['accept', 'challenge'], true));

        $status = match ($transactionStatus) {
            'pending' => PlanOrder::STATUS_PENDING,
            'deny', 'failure' => PlanOrder::STATUS_FAILED,
            'cancel', 'expire' => PlanOrder::STATUS_CANCELED,
            default => $paid ? PlanOrder::STATUS_PAID : PlanOrder::STATUS_PENDING,
        };

        return [
            'order_ref' => $orderRef,
            'status' => $status,
            'paid' => $paid,
            'transaction_id' => $payload['transaction_id'] ?? null,
            'amount' => (int) ($payload['gross_amount'] ?? 0),
        ];
    }

    public function orderRef(PlanOrder $order): string
    {
        return self::ORDER_PREFIX.$order->id;
    }

    protected function snapUrl(): string
    {
        return config('billing.midtrans.is_production', false)
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
