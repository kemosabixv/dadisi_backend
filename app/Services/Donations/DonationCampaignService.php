<?php

namespace App\Services\Donations;

use App\Exceptions\DonationCampaignException;
use App\Models\AuditLog;
use App\Models\County;
use App\Models\DonationCampaign;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use App\Services\Contracts\DonationCampaignServiceContract;

/**
 * DonationCampaignService
 *
 * Handles donation campaign management including CRUD operations,
 * publishing workflow, and status management.
 */
class DonationCampaignService implements DonationCampaignServiceContract
{
    public function listCampaigns(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = DonationCampaign::query()->with(['county', 'media']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (!empty($filters['search'])) {
            $query->where('title', 'like', "%{$filters['search']}%");
        }

        if (!empty($filters['include_deleted'])) {
            $query->withTrashed();
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function listActiveCampaigns(array $filters = [], int $perPage = 12): LengthAwarePaginator
    {
        $query = DonationCampaign::where('status', 'active')
            ->with(['county', 'media']);

        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        return $query->orderBy('published_at', 'desc')
            ->paginate($perPage);
    }

    public function getCampaignBySlug(string $slug): DonationCampaign
    {
        $campaign = DonationCampaign::where('slug', $slug)->with(['county', 'media'])->first();

        if (!$campaign) {
            throw DonationCampaignException::notFound($slug);
        }

        return $campaign;
    }

    public function getCampaign(int $id): DonationCampaign
    {
        $campaign = DonationCampaign::with(['county', 'media'])->find($id);

        if (!$campaign) {
            throw DonationCampaignException::notFound($id);
        }

        return $campaign;
    }

    public function createCampaign(Authenticatable $actor, array $data): DonationCampaign
    {
        try {
            return DB::transaction(function () use ($actor, $data) {
                $campaign = DonationCampaign::create([
                    'title' => $data['title'],
                    'slug' => Str::slug($data['title']) . '-' . Str::random(6),
                    'description' => $data['description'],
                    'goal_amount' => $data['goal_amount'],
                    'currency' => $data['currency'] ?? 'KES',
                    'county_id' => $data['county_id'] ?? null,
                    'featured_image' => $data['featured_image'] ?? null,
                    'starts_at' => $data['starts_at'] ?? null,
                    'ends_at' => $data['ends_at'] ?? null,
                    'status' => 'draft',
                    'created_by' => $actor->getAuthIdentifier(),
                ]);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'created_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'new_values' => [
                        'title' => $campaign->title,
                        'goal_amount' => $campaign->goal_amount,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                // Handle Media
                if (!empty($data['featured_media_id'])) {
                    $campaign->setFeaturedMedia($data['featured_media_id']);
                }
                if (!empty($data['gallery_media_ids'])) {
                    $campaign->addGalleryMedia($data['gallery_media_ids']);
                }

                Log::info('Campaign created', [
                    'campaign_id' => $campaign->id,
                    'created_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->load('county');
            });
        } catch (\Exception $e) {
            Log::error('Campaign creation failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::creationFailed($e->getMessage());
        }
    }

    public function updateCampaign(Authenticatable $actor, DonationCampaign $campaign, array $data): DonationCampaign
    {
        try {
            return DB::transaction(function () use ($actor, $campaign, $data) {
                $oldValues = $campaign->toArray();
                $updateData = [];

                if (isset($data['title'])) {
                    $updateData['title'] = $data['title'];
                    $updateData['slug'] = Str::slug($data['title']) . '-' . Str::random(6);
                }

                foreach (['description', 'goal_amount', 'currency', 'county_id', 'featured_image', 'starts_at', 'ends_at'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $updateData[$field] = $data[$field];
                    }
                }

                if (!empty($updateData)) {
                    $campaign->update($updateData);
                }

                // Handle Media
                if (isset($data['featured_media_id'])) {
                    $campaign->setFeaturedMedia($data['featured_media_id']);
                }
                if (isset($data['gallery_media_ids'])) {
                     $campaign->media()->wherePivot('role', 'gallery')->detach();
                     $campaign->addGalleryMedia($data['gallery_media_ids']);
                }

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'updated_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'old_values' => $oldValues,
                    'new_values' => $updateData,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Campaign updated', [
                    'campaign_id' => $campaign->id,
                    'updated_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->fresh(['county']);
            });
        } catch (\Exception $e) {
            Log::error('Campaign update failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::updateFailed($e->getMessage());
        }
    }

    public function deleteCampaign(Authenticatable $actor, DonationCampaign $campaign): bool
    {
        try {
            return DB::transaction(function () use ($actor, $campaign) {
                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'deleted_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'old_values' => ['title' => $campaign->title],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                $result = $campaign->delete();

                Log::info('Campaign deleted', [
                    'campaign_id' => $campaign->id,
                    'deleted_by' => $actor->getAuthIdentifier(),
                ]);

                return $result;
            });
        } catch (\Exception $e) {
            Log::error('Campaign deletion failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::deletionFailed($e->getMessage());
        }
    }

    public function restoreCampaign(Authenticatable $actor, string $slug): DonationCampaign
    {
        try {
            $campaign = DonationCampaign::withTrashed()->where('slug', $slug)->first();

            if (!$campaign) {
                throw DonationCampaignException::notFound($slug);
            }

            return DB::transaction(function () use ($actor, $campaign) {
                $campaign->restore();

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'restored_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'new_values' => ['restored' => true],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Campaign restored', [
                    'campaign_id' => $campaign->id,
                    'restored_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->fresh(['county']);
            });
        } catch (DonationCampaignException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Campaign restoration failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::restorationFailed($e->getMessage());
        }
    }

    public function publishCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign
    {
        try {
            return DB::transaction(function () use ($actor, $campaign) {
                $campaign->update([
                    'status' => 'active',
                    'published_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'published_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'new_values' => ['status' => 'active', 'published_at' => now()->toDateTimeString()],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Campaign published', [
                    'campaign_id' => $campaign->id,
                    'published_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Campaign publish failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::publishFailed($e->getMessage());
        }
    }

    public function unpublishCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign
    {
        try {
            return DB::transaction(function () use ($actor, $campaign) {
                $campaign->update(['status' => 'draft']);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'unpublished_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'new_values' => ['status' => 'draft'],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Campaign unpublished', [
                    'campaign_id' => $campaign->id,
                    'unpublished_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Campaign unpublish failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::unpublishFailed($e->getMessage());
        }
    }

    public function completeCampaign(Authenticatable $actor, DonationCampaign $campaign): DonationCampaign
    {
        try {
            return DB::transaction(function () use ($actor, $campaign) {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'completed_donation_campaign',
                    'model_type' => DonationCampaign::class,
                    'model_id' => $campaign->id,
                    'new_values' => ['status' => 'completed', 'completed_at' => now()->toDateTimeString()],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Campaign completed', [
                    'campaign_id' => $campaign->id,
                    'completed_by' => $actor->getAuthIdentifier(),
                ]);

                return $campaign->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Campaign complete failed', ['error' => $e->getMessage()]);
            throw DonationCampaignException::completeFailed($e->getMessage());
        }
    }

    public function getCounties(): \Illuminate\Database\Eloquent\Collection
    {
        return County::orderBy('name')->get(['id', 'name']);
    }

    public function validateCampaignForDonation(DonationCampaign $campaign, float $amount): void
    {
        if ($campaign->status !== 'active') {
            throw DonationCampaignException::notActive($campaign->slug);
        }

        if ($campaign->starts_at && $campaign->starts_at->isFuture()) {
            throw DonationCampaignException::notStarted($campaign->slug);
        }

        if ($campaign->ends_at && $campaign->ends_at->isPast()) {
            throw DonationCampaignException::ended($campaign->slug);
        }

        $effectiveMinimum = $campaign->getEffectiveMinimumAmount();
        if ($effectiveMinimum !== null && $effectiveMinimum > 0 && $amount < $effectiveMinimum) {
            throw DonationCampaignException::minimumAmountNotMet($campaign->currency, $effectiveMinimum);
        }
    }
}
