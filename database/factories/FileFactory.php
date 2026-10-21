<?php

namespace Database\Factories;

use App\File;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        $name = strtolower(Str::random(10));

        return [
            'name' => "$name.jpg",
            'file_name' => strtolower(Str::random(36)),
            'extension' => 'jpg',
            'mime' => 'image/jpeg',
            'type' => 'image',
            'parent_id' => 0,
            'meta' => [
                'sizes' => ['120x80' => false],
                'permissions' => ['public' => true],
            ],
        ];
    }
}
