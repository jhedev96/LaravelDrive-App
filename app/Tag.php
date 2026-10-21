<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'meta',
    ];

    public function files()
    {
        return $this->morphedByMany(File::class, 'taggable');
    }

    /**
     * @param  array  $ids
     * @param  null|int  $userId
     */
    public function attachFile($ids, $userId = null)
    {
        if ($userId) {
            $ids = collect($ids)->mapWithKeys(function ($id) use ($userId) {
                return [$id => ['user_id' => $userId]];
            });
        }

        $this->files()->syncWithoutDetaching($ids);
    }

    /**
     * @param  array  $ids
     * @param  null|int  $userId
     */
    public function detachFile($ids, $userId = null)
    {
        $query = $this->files();

        if ($userId) {
            $query->wherePivot('user_id', $userId);
        }

        $query->detach($ids);
    }
}
