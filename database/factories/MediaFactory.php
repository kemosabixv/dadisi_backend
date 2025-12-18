<?php

namespace Database\Factories;

use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        $types = ['image', 'audio', 'video', 'pdf', 'gif'];
        $type = $this->faker->randomElement($types);

        $mimeTypes = [
            'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'],
            'audio' => ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm'],
            'video' => ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'],
            'pdf' => ['application/pdf'],
            'gif' => ['image/gif'],
        ];

        $fileName = $this->faker->word() . '.' . match($type) {
            'image' => $this->faker->randomElement(['jpg', 'png', 'webp']),
            'audio' => $this->faker->randomElement(['mp3', 'wav', 'ogg']),
            'video' => $this->faker->randomElement(['mp4', 'webm', 'mov']),
            'pdf' => 'pdf',
            'gif' => 'gif',
        };

        return [
            'user_id' => User::factory(),
            'file_name' => $fileName,
            'file_path' => '/media/2025-12/' . $fileName,
            'type' => $type,
            'mime_type' => $this->faker->randomElement($mimeTypes[$type]),
            'file_size' => $this->faker->numberBetween(1000, 5000000),
            'is_public' => $this->faker->boolean(25),
            'attached_to' => null,
            'attached_to_id' => null,
            'owner_type' => null,
            'owner_id' => null,
        ];
    }

    public function withUser(User $user = null): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function forUser(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function image(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'mime_type' => 'image/jpeg',
            'file_name' => $this->faker->word() . '.jpg',
        ]);
    }

    public function audio(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'audio',
            'mime_type' => 'audio/mpeg',
            'file_name' => $this->faker->word() . '.mp3',
        ]);
    }

    public function video(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'mime_type' => 'video/mp4',
            'file_name' => $this->faker->word() . '.mp4',
        ]);
    }

    public function pdf(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_name' => $this->faker->word() . '.pdf',
        ]);
    }
}
