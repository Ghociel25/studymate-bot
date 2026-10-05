<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    protected $model = File::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'original_name' => fake()->word().'.txt',
            'mime_type' => 'text/plain',
            'size' => fake()->numberBetween(100, 10000),
            'storage_path' => 'telegram-files/test/'.fake()->uuid().'.txt',
            'processing_status' => 'processed',
        ];
    }
}
