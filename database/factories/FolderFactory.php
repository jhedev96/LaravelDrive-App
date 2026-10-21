<?php

namespace Database\Factories;

use App\Folder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Folder>
 */
class FolderFactory extends Factory
{
    protected $model = Folder::class;

    public function definition(): array
    {
        return [
            'name' => 'Folder '.$this->faker->unique()->word(),
            'file_name' => $this->faker->unique()->word(),
            'type' => 'folder',
            'parent_id' => 0,
        ];
    }
}
