<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Storage subscription plans (D18). `max_storage` is enforced by
 * App\Services\StorageQuota only while billing.enabled is true.
 */
class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'max_storage',
        'price',
        'currency',
        'active',
    ];

    protected $casts = [
        'max_storage' => 'integer',
        'price' => 'integer',
        'active' => 'boolean',
    ];

    /**
     * Reseeder-friendly lookup for the default (free) plan.
     */
    public static function default(): ?self
    {
        return static::query()->where('slug', config('billing.default_plan_slug', 'free'))->first();
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
