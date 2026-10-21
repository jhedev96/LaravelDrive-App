<?php

namespace App;

use App\Observers\FolderObserver;
use App\Traits\HandlesPaths;
use App\Traits\HashesId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy(FolderObserver::class)]
class Folder extends File
{
    protected $table = 'files';

    protected $attributes = ['type' => 'folder'];

    /**
     * Filter to folders and attach the "stared" tag subselect,
     * matching the previous newQuery() override behavior.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('fsType', function (Builder $query) {
            $query->where('type', '=', 'folder');
        });
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->withoutGlobalScope('fsType');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'taggables', 'taggable_id', 'tag_id');
    }
}
