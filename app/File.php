<?php

namespace App;

use App\Observers\FileObserver;
use App\Traits\HandlesPaths;
use App\Traits\HashesId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[ObservedBy(FileObserver::class)]
class File extends Model
{
    use HandlesPaths, HashesId, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'path',
        'file_name',
        'extension',
        'mime',
        'type',
        'public_path',
        'parent_id',
        'driver',
        'driver_data',
        'uploaded_by',
        'meta',
    ];

    protected $casts = [
        'id' => 'integer',
        'file_size' => 'integer',
        'parent_id' => 'integer',
        'meta' => 'array',
    ];

    protected $hidden = [
        'meta',
        'file_name',
    ];

    protected $appends = [
        'hash',
        'stared',
    ];

    /**
     * Attach the "stared" tag subselect (and the folder-type filter, coming via scopeStared)
     * to every query made through this model, matching the previous newQuery() behavior.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('stared', function (Builder $query) {
            $query->addSelect(['stared' => function ($query) {
                $query->select('name')
                    ->from('tags')
                    ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
                    ->whereColumn('taggables.taggable_id', 'files.id')
                    ->limit(1);
            }]);
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function getStoragePath()
    {
        return "$this->file_name/$this->name";
    }

    /**
     * Get path of specified entry.
     *
     * @param  int  $id
     * @return string
     */
    public function findPath($id)
    {
        $entry = self::find($id, ['path']);

        return $entry ? $entry->getOriginal('path') : '';
    }

    /**
     * Return file entry name with extension.
     */
    public function getNameWithExtension()
    {
        if (! $this->exists) {
            return '';
        }

        $extension = pathinfo($this->name, PATHINFO_EXTENSION);

        if (! $extension) {
            return $this->name.'.'.$this->extension;
        }

        return $this->name;
    }

    public function tags()
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function getStaredAttribute()
    {
        return ! empty($this->attributes['stared']);
    }

    public function scopeStared($query)
    {
        return $query->addSelect(['stared' => function ($query) {
            $query->select('name')
                ->from('tags')
                ->join('taggables', 'tags.id', '=', 'taggables.tag_id')
                ->whereColumn('taggables.taggable_id', 'files.id')
                ->limit(1);
        }]);
    }

    public function sharedWith()
    {
        return $this->belongsToMany(User::class, 'file_user', 'file_id', 'user_id')
            ->withPivot(['permissions', 'created_at', 'updated_at', 'owner']);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    public function setPermission($permission, $value)
    {
        $meta = $this->meta ?? [];
        $permissions = $meta['permissions'] ?? [];
        $permissions[$permission] = $value;
        $meta['permissions'] = $permissions;
        $this->meta = $meta;
        $this->save();
    }

    public function getPermission($permission = null)
    {
        $permissions = $this->meta['permissions'] ?? [];

        if ($permission !== null) {
            if (array_key_exists($permission, $permissions)) {
                return $permissions[$permission];
            }

            return false;
        }

        return $permissions;
    }

    public function hasPermission($permission)
    {
        return $this->getPermission($permission) === true;
    }

    public function setImageSize($size)
    {
        $meta = $this->meta ?? [];
        $sizes = $meta['sizes'] ?? [];
        $sizes[$size] = true;
        $meta['sizes'] = $sizes;
        $this->meta = $meta;
        $this->save();
    }

    public function getImageSizes()
    {
        return $this->meta['sizes'] ?? [];
    }

    public function updatePublicPaths($driver = null)
    {
        if ($driver) {
            $this->driver = $driver;
        }

        if ($this->hasPermission('public')) {
            $this->public_path = Storage::disk($this->driver ?: 'local')->url($this->getStoragePath());
        } else {
            $this->public_path = Storage::disk('local')->url($this->getStoragePath());
        }

        $this->save();
    }
}
