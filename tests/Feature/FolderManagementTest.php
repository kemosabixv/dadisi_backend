<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_create_a_personal_folder()
    {
        $response = $this->actingAs($this->user)
            ->postJson(route('folders.store'), [
                'name' => 'My Work',
                'root_type' => 'personal',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'My Work');

        $this->assertDatabaseHas('folders', [
            'name' => 'My Work',
            'user_id' => $this->user->id,
            'root_type' => 'personal',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_list_root_personal_folders()
    {
        Folder::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'root_type' => 'personal',
            'parent_id' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('folders.index') . '?root_type=personal');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_list_subfolders()
    {
        $parent = Folder::factory()->create([
            'user_id' => $this->user->id,
            'root_type' => 'personal',
        ]);

        Folder::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'parent_id' => $parent->id,
            'root_type' => 'personal',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('folders.index') . "?parent_id={$parent->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_rename_a_folder()
    {
        $folder = Folder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->putJson(route('folders.update', $folder->id), [
                'name' => 'Renamed Folder',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('Renamed Folder', $folder->refresh()->name);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cannot_rename_system_folders()
    {
        $folder = Folder::factory()->create([
            'user_id' => $this->user->id,
            'is_system' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson(route('folders.update', $folder->id), [
                'name' => 'Should Fail',
            ]);

        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_delete_an_empty_folder()
    {
        $folder = Folder::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('folders.destroy', $folder->id));

        $response->assertStatus(200);
        $this->assertSoftDeleted('folders', ['id' => $folder->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_cannot_delete_non_empty_folder_without_force()
    {
        $folder = Folder::factory()->create(['user_id' => $this->user->id]);
        Media::factory()->create(['user_id' => $this->user->id, 'folder_id' => $folder->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson(route('folders.destroy', $folder->id));

        $response->assertStatus(422);
        $this->assertDatabaseHas('folders', ['id' => $folder->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_bulk_move_media_to_a_folder()
    {
        $folder = Folder::factory()->create(['user_id' => $this->user->id]);
        $mediaIds = Media::factory()->count(3)->create(['user_id' => $this->user->id])->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson(route('media.bulk-move'), [
                'media_ids' => $mediaIds,
                'folder_id' => $folder->id,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(3, Media::where('folder_id', $folder->id)->count());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_isolates_folders_between_users()
    {
        $otherUser = User::factory()->create();
        $otherFolder = Folder::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson(route('folders.index') . "?parent_id={$otherFolder->id}");

        // Result should be empty or unauthorized if specifically requested via parent_id check?
        // Actually FolderController::index lists folders where user_id matches. 
        // If we request otherFolder->id, it will return nothing because user_id doesn't match.
        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Try to rename other user's folder
        $response = $this->actingAs($this->user)
            ->putJson(route('folders.update', $otherFolder->id), ['name' => 'Hacked']);
        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_media_by_folder_id()
    {
        $folder1 = Folder::factory()->create(['user_id' => $this->user->id]);
        $folder2 = Folder::factory()->create(['user_id' => $this->user->id]);

        Media::factory()->count(2)->create(['user_id' => $this->user->id, 'folder_id' => $folder1->id]);
        Media::factory()->count(3)->create(['user_id' => $this->user->id, 'folder_id' => $folder2->id]);
        Media::factory()->count(1)->create(['user_id' => $this->user->id, 'folder_id' => null]);

        // Filter by folder 1
        $response = $this->actingAs($this->user)
            ->getJson(route('media.index') . "?folder_id={$folder1->id}");
        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Filter by root (folder_id = null)
        $response = $this->actingAs($this->user)
            ->getJson(route('media.index'));
        // Wait, how does index handle null? 
        // MediaService::listMedia: if folder_id is not provided, does it filter?
        // Let's check MediaService.php:60
    }
}
