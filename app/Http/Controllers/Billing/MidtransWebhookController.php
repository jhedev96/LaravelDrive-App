<?php

namespace App\Http\Controllers\Billing;

use App\PlanOrder;
use App\Services\Billing\Contracts\PaymentGateway;
use App\Services\Billing\MidtransGateway;
use App\Services\Billing\PlanActivator;
use Illuminate\Http\Request;
use Throwable;

/**
 * HTTP notification endpoint for Midtrans (Snap QRIS callbacks).
 *
 * Lives outside the auth:api group in the api routes; it guards itself
 * via the sha512 signature check, so CSRF/auth are not used here.
 */
class MidtransWebhookController extends \App\Http\Controllers\Controller
{
    /**
     * Unrecognized orders answer 2xx so Midtrans does not retry forever;
     * unverifiable signatures answer 403.
     */
    public function __invoke(Request $request, PaymentGateway $gateway)
    {
        try {
            $notification = $gateway->parseNotification($request->all());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'invalid_signature'], 403);
        }

        $orderId = $this->orderRefToId($notification['order_ref']);
        $order = $orderId ? PlanOrder::query()->find($orderId) : null;

        if ($order === null) {
            return response()->noContent();
        }

        // guard against amount tampering between Snap creation and payment
        if ((int) $notification['amount'] !== (int) $order->amount) {
            report('Midtrans notification amount mismatch for order '.$order->id);

            return response()->json(['error' => 'amount_mismatch'], 400);
        }

        $order->status = $notification['status'];
        $order->gateway_txn_id = $notification['transaction_id'];
        $order->payload = array_merge($order->payload ?? [], ['notification' => $request->all()]);
        $order->paid_at = $notification['paid'] ? now() : $order->paid_at;
        $order->save();

        if ($notification['paid']) {
            app(PlanActivator::class)->activate($order->user, $order);
        }

        return response()->noContent();
    }

    protected function orderRefToId(string $ref): ?int
    {
        if (preg_match('/^'.MidtransGateway::ORDER_PREFIX.'(\d+)$/', $ref, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }
}
