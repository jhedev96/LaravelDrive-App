<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ShareableLink extends Model
{
    protected $table = 'shareable_links';

    protected $guarded = ['id'];

    protected $fillable = [
        'hash',
        'user_id',
        'file_id',
        'allow_edit',
        'allow_download',
        'password',
        'expires_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'allow_download' => 'boolean',
        'allow_edit' => 'boolean',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'expires_at',
        'hash',
        'user_id',
        'file_id',
    ];

    protected $appends = [
        'link',
        'requires_password',
    ];

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function getLinkAttribute()
    {
        return route('shareable', ['hash' => $this->hash]);
    }

    public function getRequiresPasswordAttribute()
    {
        return ! empty($this->attributes['password']);
    }

    public function setHashAttribute($value)
    {
        $this->attributes['hash'] = $value ? $value : \Str::random(30);
    }

    public function setExpiresAtAttribute($value)
    {
        if (empty($value)) {
            $this->attributes['expires_at'] = null;

            return;
        }

        $this->attributes['expires_at'] = Carbon::parse($value);
    }
}
