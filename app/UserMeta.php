<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserMeta extends Model
{
    protected $table = 'user_metas';

    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    protected $hidden = [
        'hide',
    ];

    public function setValueAttribute($value)
    {
        if (! empty($value)) {
            $this->attributes['value'] = maybe_serialize($value);
        }
    }

    public function getValueAttribute($value)
    {
        return maybe_unserialize($value);
    }

    /**
     * Get the user that owns the meta.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
