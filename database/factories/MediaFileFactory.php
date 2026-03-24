<?php

namespace Database\Factories;

use App\Models\MediaFile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFileFactory extends Factory
{
    protected $model = MediaFile::class;

    public function definition(): array
    {
        $hash = $this->faker->sha256;
        return [
            'hash' => $hash,
            'disk' => 'r2',
            'path' => 'blobs/' . substr($hash, 0, 2) . '/' . substr($hash, 2, 2) . '/' . $hash,
            'size' => $this->faker->numberBetween(1000, 10000000),
            'mime_type' => $this->faker->mimeType(),
            'ref_count' => 1,
        ];
    }
}
