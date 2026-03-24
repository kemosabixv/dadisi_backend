<?php

namespace Tests\Unit\Services;

use App\DTOs\CreateLabSpaceDTO;
use App\DTOs\UpdateLabSpaceDTO;
use App\Models\LabSpace;
use App\Models\Media;
use App\Models\User;
use App\Services\LabManagement\LabService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabServiceMediaTest extends TestCase
{
    use RefreshDatabase;

    protected LabService $labService;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('r2');

        $this->user = User::factory()->create();
        $this->labService = app(LabService::class);
    }

    #[Test]
    public function it_attaches_media_during_lab_creation()
    {
        $featuredMedia = Media::factory()->for($this->user)->create();
        $galleryMedia = Media::factory()->for($this->user)->count(2)->create();

        $dto = new CreateLabSpaceDTO(
            name: 'Test Lab',
            type: 'wet_lab',
            county: 1,
            featured_media_id: $featuredMedia->id,
            gallery_media_ids: $galleryMedia->pluck('id')->toArray()
        );

        $lab = $this->labService->createLabSpace($this->user, $dto);

        $this->assertEquals($featuredMedia->id, $lab->featuredMedia()->id);
        $this->assertCount(2, $lab->galleryMedia()->get());
        $this->assertEquals(1, $featuredMedia->fresh()->usage_count);
        foreach ($galleryMedia as $media) {
            $this->assertEquals(1, $media->fresh()->usage_count);
        }
    }

    #[Test]
    public function it_updates_media_during_lab_update()
    {
        $lab = LabSpace::factory()->create();
        $oldFeatured = Media::factory()->for($this->user)->create();
        $lab->setFeaturedMedia($oldFeatured->id);

        $newFeatured = Media::factory()->for($this->user)->create();
        $newGallery = Media::factory()->for($this->user)->count(2)->create();

        $dto = new UpdateLabSpaceDTO(
            featured_media_id: $newFeatured->id,
            gallery_media_ids: $newGallery->pluck('id')->toArray()
        );

        $this->labService->updateLabSpace($this->user, $lab, $dto);

        $this->assertEquals($newFeatured->id, $lab->featuredMedia()->id);
        $this->assertCount(2, $lab->galleryMedia()->get());
        
        $this->assertEquals(0, $oldFeatured->fresh()->usage_count);
        $this->assertEquals(1, $newFeatured->fresh()->usage_count);
        foreach ($newGallery as $media) {
            $this->assertEquals(1, $media->fresh()->usage_count);
        }
    }
}
