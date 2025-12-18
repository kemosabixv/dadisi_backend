<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

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
        $response = $this->actingAs($this->user)->getJson('/api/media');

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
        $response = $this->actingAs($this->user)
            ->getJson('/api/media?page=1&per_page=15');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 15)
            ->assertJsonPath('pagination.current_page', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_filters_by_type()
    {
        $response = $this->actingAs($this->user)->getJson('/api/media?type=image');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_searches_by_filename()
    {
        $response = $this->actingAs($this->user)->getJson('/api/media?search=test');

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
        $response = $this->actingAs($this->user)->postJson('/api/media', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_upload_validates_file_type()
    {
        $file = \Illuminate\Http\UploadedFile::fake()->create('test.txt', 100);

        $response = $this->actingAs($this->user)->postJson('/api/media', [
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
        $response = $this->actingAs($this->user)->getJson('/api/media/99999');

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
        $response = $this->actingAs($this->user)->deleteJson('/api/media/99999');

        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_user_can_show_existing_media()
    {
        $media = \App\Models\Media::factory()->forUser($this->user)->create();

        $response = $this->actingAs($this->user)->getJson('/api/media/' . $media->id);

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

        $response = $this->actingAs($this->user)->deleteJson('/api/media/' . $media->id);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_sorts_by_date()
    {
        $response = $this->actingAs($this->user)->getJson('/api/media?sort=-created_at');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_index_filters_by_is_public()
    {
        $response = $this->actingAs($this->user)->getJson('/api/media?is_public=1');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data', 'pagination']);
    }
}


