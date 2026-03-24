<?php

namespace App\Services\LabManagement;

use App\DTOs\CreateLabSpaceDTO;
use App\DTOs\UpdateLabSpaceDTO;
use App\Exceptions\LabException;
use App\Models\AuditLog;
use App\Models\LabSpace;
use App\Models\Media;
use App\Services\Media\MediaService;
use App\Services\Contracts\LabManagementServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * LabService
 *
 * Handles lab management including creation, updates, and deletion.
 */
class LabService implements LabManagementServiceContract
{
    /**
     * Create a new lab space
     *
     *
     * @throws LabException
     */
    public function createLabSpace(Authenticatable $creator, CreateLabSpaceDTO $dto): LabSpace
    {
        try {
            return DB::transaction(function () use ($creator, $dto) {
                $data = $dto->toArray();
                $lab = LabSpace::create([
                    'name' => $data['name'],
                    'slug' => $data['slug'] ?? Str::slug($data['name']),
                    'type' => $data['type'] ?? null,
                    'county' => $data['county'] ?? 'Nairobi',
                    'description' => $data['description'] ?? null,
                    'location' => $data['location'] ?? null,
                    'capacity' => $data['capacity'] ?? 4,
                    'equipment_list' => $data['equipment_list'] ?? [],
                    'safety_requirements' => $data['safety_requirements'] ?? [],
                    'is_available' => $data['is_available'] ?? true,
                    'hourly_rate' => $data['hourly_rate'] ?? 0,
                ]);

                AuditLog::create([
                    'user_id' => $creator->getAuthIdentifier(),
                    'action' => 'created_lab_space',
                    'model_type' => LabSpace::class,
                    'model_id' => $lab->id,
                    'new_values' => $lab->toArray(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('LabSpace created', [
                    'lab_id' => $lab->id,
                    'creator_id' => $creator->getAuthIdentifier(),
                ]);

                // Handle Media
                if (! empty($data['featured_media_id'])) {
                    $media = Media::find($data['featured_media_id']);
                    if ($media) {
                        app(MediaService::class)->promoteToPublic($media, 'lab-spaces', $lab->slug);
                        $lab->setFeaturedMedia($media->id);
                    }
                }
                if (! empty($data['gallery_media_ids'])) {
                    foreach ($data['gallery_media_ids'] as $mediaId) {
                        $media = Media::find($mediaId);
                        if ($media) {
                            app(MediaService::class)->promoteToPublic($media, 'lab-spaces', $lab->slug);
                        }
                    }
                    $lab->addGalleryMedia($data['gallery_media_ids']);
                }

                return $lab->load('media');
            });
        } catch (\Exception $e) {
            Log::error('LabSpace creation failed', [
                'creator_id' => $creator->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);

            throw LabException::creationFailed($e->getMessage());
        }
    }

    /**
     * Update a lab space
     *
     *
     * @throws LabException
     */
    public function updateLabSpace(Authenticatable $actor, LabSpace $lab, UpdateLabSpaceDTO $dto): LabSpace
    {
        try {
            $data = array_filter($dto->toArray(), fn ($v) => $v !== null);
            $oldValues = $lab->toArray();
            $lab->update($data);

            AuditLog::create([
                'user_id' => $actor->getAuthIdentifier(),
                'action' => 'updated_lab_space',
                'model_type' => LabSpace::class,
                'model_id' => $lab->id,
                'old_values' => $oldValues,
                'new_values' => $lab->fresh()->toArray(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('LabSpace updated', [
                'lab_id' => $lab->id,
                'updated_by' => $actor->getAuthIdentifier(),
            ]);

            // Handle Media
            if (isset($data['featured_media_id'])) {
                if ($data['featured_media_id']) {
                    $media = Media::find($data['featured_media_id']);
                    if ($media) {
                        app(MediaService::class)->promoteToPublic($media, 'lab-spaces', $lab->slug);
                        $lab->setFeaturedMedia($media->id);
                    }
                } else {
                    $lab->setFeaturedMedia(null);
                }
            }
            if (isset($data['gallery_media_ids'])) {
                $lab->media()->wherePivot('role', 'gallery')->detach();
                if (! empty($data['gallery_media_ids'])) {
                    foreach ($data['gallery_media_ids'] as $mediaId) {
                        $media = Media::find($mediaId);
                        if ($media) {
                            app(MediaService::class)->promoteToPublic($media, 'lab-spaces', $lab->slug);
                        }
                    }
                    $lab->addGalleryMedia($data['gallery_media_ids']);
                }
            }

            return $lab->fresh()->load('media');
        } catch (\Exception $e) {
            throw LabException::updateFailed($e->getMessage());
        }
    }

    /**
     * Delete a lab space
     *
     *
     * @throws LabException
     */
    public function deleteLabSpace(Authenticatable $actor, LabSpace $lab): bool
    {
        try {
            return DB::transaction(function () use ($actor, $lab) {
                $labId = $lab->id;
                $labName = $lab->name;

                $lab->delete();

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'deleted_lab_space',
                    'model_type' => LabSpace::class,
                    'model_id' => $labId,
                    'old_values' => ['name' => $labName],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('LabSpace deleted', [
                    'lab_id' => $labId,
                    'deleted_by' => $actor->getAuthIdentifier(),
                ]);

                return true;
            });
        } catch (\Exception $e) {
            throw LabException::deletionFailed($e->getMessage());
        }
    }

    /**
     * List lab spaces with filtering
     */
    public function listLabSpaces(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = LabSpace::query();

        if (isset($filters['type']) && $filters['type']) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where('name', 'like', "%{$filters['search']}%")
                ->orWhere('description', 'like', "%{$filters['search']}%");
        }

        return $query->orderBy('name', 'asc')->paginate($perPage);
    }

    /**
     * Get lab spaces by county
     */
    public function getLabSpacesByCounty(string $county): \Illuminate\Database\Eloquent\Collection
    {
        return LabSpace::where('county', $county)
            ->where('is_available', true)
            ->get();
    }
}

/*
class EquipmentService implements \App\Services\Contracts\EquipmentServiceContract
{
    // Equipment management is currently disabled due to missing models/migrations
}
*/
