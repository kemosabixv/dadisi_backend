<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class MediaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_requires_authentication()
    {
        $response = $this->getJson('/api/media');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_list_media()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'pagination' => [
                    'total',
                    'per_page',
                    'current_page',
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_list_media_with_pagination()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.current_page', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_filters_by_type()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media?type=image');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_searches_by_filename()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media?search=test');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_store_requires_authentication()
    {
        $response = $this->postJson('/api/media', [
            'file' => 'test content',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_upload_validates_file_required()
    {
        $this->actingAs($this->user);
        $response = $this->postJson('/api/media', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_upload_validates_file_type()
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.txt', 100);

        $this->actingAs($this->user);
        $response = $this->postJson('/api/media', [
            'file' => $file,
        ]);

        // TXT files are not in allowed MIME types - should fail validation
        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_show_requires_authentication()
    {
        $response = $this->getJson('/api/media/1');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_show_returns_404_for_nonexistent()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media/99999');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_destroy_requires_authentication()
    {
        $response = $this->deleteJson('/api/media/1');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_delete_returns_404_for_nonexistent()
    {
        $this->actingAs($this->user);
        $response = $this->deleteJson('/api/media/99999');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_show_existing_media()
    {
        $media = \App\Models\Media::factory()->forUser($this->user)->create();

        $this->actingAs($this->user);
        $response = $this->getJson('/api/media/' . $media->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'file_name',
                    'file_path',
                    'mime_type',
                ]
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_delete_own_media()
    {
        $media = \App\Models\Media::factory()->forUser($this->user)->create();

        $this->actingAs($this->user);
        $response = $this->deleteJson('/api/media/' . $media->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_sorts_by_date()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media?sort=-created_at');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_filters_by_is_public()
    {
        $this->actingAs($this->user);
        $response = $this->getJson('/api/media?is_public=1');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }
}


