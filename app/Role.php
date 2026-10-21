<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'permissions',
        'status',
    ];

    protected $casts = [
        'id' => 'integer',
        'status' => 'integer',
    ];

    public function setPermissionsAttribute($value)
    {
        if (! empty($value)) {
            $this->attributes['permissions'] = serialize($value);
        }
    }

    public function getPermissionsAttribute($value)
    {
        if ($value && is_serialized($value)) {
            return (array) @unserialize($value);
        }

        return [];
    }

    /**
     * The users that belong to the role.
     */
    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
