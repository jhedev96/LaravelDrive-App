<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One purchase attempt (QRIS order via Midtrans or manual admin
 * activation). The plan itself is granted in activatePlan().
 */
class PlanOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_FAILED = 'failed';

    public const GATEWAY_MIDTRANS = 'midtrans';

    public const GATEWAY_XENDIT = 'xendit';

    public const GATEWAY_ADMIN = 'admin';

    protected $fillable = [
        'user_id',
        'plan_id',
        'amount',
        'currency',
        'status',
        'gateway',
        'gateway_txn_id',
        'payload',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
