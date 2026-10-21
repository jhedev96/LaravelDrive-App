<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Generic pivot-side model for the "taggables" morph relation.
 * Kept for BC with the previous codebase structure.
 */
class Tagable extends Model
{
    protected $table = 'taggables';
}
