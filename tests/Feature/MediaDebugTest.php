<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaDebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_factory_works()
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create();
        
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        $this->assertEquals(1, Media::count());
    }
}
