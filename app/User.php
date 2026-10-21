<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements OAuthenticatable
{
    use Notifiable, HasApiTokens, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'firstname',
        'lastname',
        'status',
        'permissions',
        'avatar',
        'last_loged_in',
        'ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
        'last_loged_in' => 'datetime',
        'plan_id' => 'integer',
        'plan_expires_at' => 'datetime',
    ];

    protected $with = ['roles'];

    public static array $statusLabels = [
        0 => 'inactive',
        1 => 'active',
        2 => 'pending',
        3 => 'banned',
    ];

    public function getStatusAttribute($value)
    {
        if (array_key_exists($value, self::$statusLabels)) {
            return self::$statusLabels[$value];
        }

        return $value;
    }

    public function setStatusAttribute($value)
    {
        if (is_numeric($value) || ctype_digit((string) $value)) {
            $this->attributes['status'] = (int) $value;

            return;
        }

        $this->attributes['status'] = array_search(strtolower($value), self::$statusLabels);
    }

    public function setPermissionsAttribute($value)
    {
        if (! empty($value)) {
            $this->attributes['permissions'] = serialize($value);
        }
    }

    public function getPermissionsAttribute($value)
    {
        $permissions = [];

        if ($value) {
            $permissions = (array) (is_serialized($value) ? @unserialize($value) : []);
        }

        $roles = $this->roles;

        foreach ($roles as $role) {
            $permissions = array_merge($permissions, $role->permissions);
        }

        return empty($permissions) ? [] : array_values(array_unique($permissions));
    }

    public function hasPermission($permission)
    {
        return in_array($permission, $this->permissions, true);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Billing plan (D18). Null plan = behave as the default/free plan.
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function planOrders()
    {
        return $this->hasMany(PlanOrder::class);
    }

    /**
     * Total size of every entry this user has uploaded (trashed entries
     * included — they still occupy the storage until purged).
     */
    public function storageUsed(): int
    {
        return (int) File::query()->where('uploaded_by', $this->id)->sum('file_size');
    }

    public function meta()
    {
        return $this->hasMany(UserMeta::class);
    }

    public function sharedFiles()
    {
        return $this->belongsToMany(File::class, 'file_user', 'user_id', 'file_id')
            ->withPivot(['permissions', 'created_at', 'updated_at', 'owner']);
    }

    /**
     * Legacy snake_case alias kept for compatibility with the previous API responses.
     */
    public function shared_files()
    {
        return $this->sharedFiles();
    }

    public function getDisplayNameAttribute()
    {
        return trim(($this->firstname ?? '').' '.($this->lastname ?? '')) ?: $this->name;
    }

    public function getAvatarAttribute($value)
    {
        $value = is_array($value) ? $value : json_decode($value);

        if (empty($value) || empty($value->avatar)) {
            $value = new \stdClass();
            $value->avatar = $this->get_gravatar($this->email, 200);
        }

        return $value;
    }

    protected function get_gravatar($email, $s = 40, $d = 'mp', $r = 'g', $img = false, $atts = [])
    {
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email ?? '')));
        $url .= "?s=$s&d=$d&r=$r";
        if ($img) {
            $url = '<img src="'.htmlspecialchars($url, ENT_QUOTES).'"';
            foreach ($atts as $key => $val) {
                $url .= ' '.$key.'="'.htmlspecialchars($val, ENT_QUOTES).'"';
            }
            $url .= ' />';
        }

        return $url;
    }
}
