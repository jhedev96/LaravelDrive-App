<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Extra-curricular registry of translation languages that keeps the old
 * translation UI contract (locale + name) alive on top of the
 * spatie/laravel-translation-loader storage.
 */
class Language extends Model
{
    protected $fillable = [
        'locale',
        'name',
    ];

    protected $casts = [
        'id' => 'integer',
    ];
}
