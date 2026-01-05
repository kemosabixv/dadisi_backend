<?php

namespace App\Services\Donations;

use App\Exceptions\DonationException;
use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\User;
use App\Models\County;
use App\Services\Contracts\DonationServiceContract;
use App\Services\Contracts\PaymentServiceContract;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DonationService
 *
 * Handles donation management including creation, verification,
 * tracking, and reporting with comprehensive audit logging.
 */
class DonationService implements DonationServiceContract
{
    /**
     * @param PaymentServiceContract $paymentService
     */
    public function __construct(protected PaymentServiceContract $paymentService) {}

    /**
     * Create a new donation
     */
    public function createDonation(?Authenticatable $donor, array $data): Donation
    {
        try {
            return DB::transaction(function () use ($donor, $data) {
                $donationData = [
                    'user_id' => $donor?->getAuthIdentifier(),
                    'donor_name' => $data['donor_name'],
                    'donor_email' => $data['donor_email'],
                    'donor_phone' => $data['donor_phone'] ?? null,
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? 'KES',
                    'county_id' => $data['county_id'],
                    'campaign_id' => $data['campaign_id'] ?? null,
                    'reference' => $data['reference'] ?? Donation::generateReference(),
                    'notes' => $data['notes'] ?? null,
                    'status' => 'pending',
                ];

                $donation = Donation::create($donationData);

                AuditLog::create([
                    'user_id' => $donor?->getAuthIdentifier(),
                    'action' => 'created_donation',
                    'model_type' => Donation::class,
                    'model_id' => $donation->id,
                    'new_values' => [
                        'amount' => $donation->amount,
                        'currency' => $donation->currency,
                        'reference' => $donation->reference,
                    ],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Donation initiated', [
                    'donation_id' => $donation->id,
                    'amount' => $donation->amount,
                    'user_id' => $donor?->getAuthIdentifier(),
                ]);

                return $donation;
            });
        } catch (\Exception $e) {
            Log::error('Donation creation failed', [
                'user_id' => $donor?->getAuthIdentifier(),
                'error' => $e->getMessage(),
            ]);
            throw DonationException::creationFailed($e->getMessage());
        }
    }

    /**
     * Get donation by ID
     */
    public function getDonation(string $id): Donation
    {
        try {
            return Donation::with(['user', 'county', 'campaign', 'payment'])->findOrFail($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw DonationException::notFound($id);
        }
    }

    /**
     * Get donation by reference
     */
    public function getDonationByReference(string $reference): Donation
    {
        try {
            return Donation::with(['user', 'county', 'campaign', 'payment'])
                ->where('reference', $reference)
                ->firstOrFail();
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw DonationException::notFound($reference);
        }
    }

    /**
     * List donations with filtering
     */
    public function listDonations(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Donation::query()->with(['user', 'county', 'campaign']);

        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('donor_name', 'like', "%{$filters['search']}%")
                  ->orWhere('donor_email', 'like', "%{$filters['search']}%")
                  ->orWhere('reference', 'like', "%{$filters['search']}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Mark donation as paid
     */
    public function markAsPaid(Donation $donation, array $paymentData = [], ?Authenticatable $actor = null): Donation
    {
        // Prevent marking an already paid donation as paid
        if ($donation->status === 'paid') {
            throw DonationException::alreadyPaid($donation->id);
        }

        try {
            return DB::transaction(function () use ($donation, $paymentData, $actor) {
                $oldStatus = $donation->status;
                $donation->update([
                    'status' => 'paid',
                    'receipt_number' => Donation::generateReceiptNumber(),
                    'payment_id' => $paymentData['payment_id'] ?? $donation->payment_id,
                ]);

                AuditLog::create([
                    'user_id' => $actor?->getAuthIdentifier(),
                    'action' => 'marked_donation_paid',
                    'model_type' => Donation::class,
                    'model_id' => $donation->id,
                    'old_values' => ['status' => $oldStatus],
                    'new_values' => ['status' => 'paid'],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                Log::info('Donation marked as paid', [
                    'donation_id' => $donation->id,
                    'reference' => $donation->reference,
                ]);

                return $donation->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Failed to mark donation as paid', [
                'donation_id' => $donation->id,
                'error' => $e->getMessage(),
            ]);
            throw DonationException::verificationFailed($e->getMessage());
        }
    }

    /**
     * Delete/Cancel donation
     */
    public function deleteDonation(Authenticatable $actor, Donation $donation): bool
    {
        /** @var \App\Models\User $actor */
        if (!$donation->isPending() && !$actor->isAdmin()) {
            throw DonationException::onlyPendingCanBeCancelled();
        }

        // Check ownership if not admin
        if (!$actor->isAdmin() && $donation->user_id !== $actor->getAuthIdentifier()) {
            throw DonationException::unauthorized('cancel');
        }

        try {
            return DB::transaction(function () use ($actor, $donation) {
                AuditLog::create([
                    'user_id' => $actor->getAuthIdentifier(),
                    'action' => 'cancelled_donation',
                    'model_type' => Donation::class,
                    'model_id' => $donation->id,
                    'old_values' => ['status' => $donation->status],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $donation->delete();
            });
        } catch (\Exception $e) {
            Log::error('Failed to cancel donation', [
                'donation_id' => $donation->id,
                'error' => $e->getMessage(),
            ]);
            throw DonationException::creationFailed('Failed to cancel donation: ' . $e->getMessage());
        }
    }

    /**
     * Get donor donations history
     */
    public function getDonorHistory(Authenticatable $donor, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return Donation::where('user_id', $donor->getAuthIdentifier())
            ->with(['county', 'campaign'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get statistics
     */
    public function getStatistics(array $filters = []): array
    {
        $query = Donation::query();

        if (!empty($filters['county_id'])) {
            $query->where('county_id', $filters['county_id']);
        }

        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        return [
            'total_donations' => $query->count(),
            'total_amount' => (float) $query->sum('amount'),
            'paid_count' => (clone $query)->where('status', 'paid')->count(),
            'paid_amount' => (float) (clone $query)->where('status', 'paid')->sum('amount'),
            'pending_count' => (clone $query)->where('status', 'pending')->count(),
            'failed_count' => (clone $query)->where('status', 'failed')->count(),
            'average_amount' => $query->count() > 0 ? (float) ($query->sum('amount') / $query->count()) : 0,
        ];
    }

    /**
     * Generate donation report
     */
    public function generateReport(array $filters, string $format = 'csv'): string
    {
        try {
            $donations = $this->listDonations($filters, 1000);

            if ($format === 'json') {
                return json_encode([
                    'report' => $donations->items(),
                    'total_amount' => collect($donations->items())->sum('amount'),
                    'total_count' => $donations->total(),
                ]);
            }

            // CSV Implementation
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['ID', 'Reference', 'Donor', 'Email', 'Amount', 'Currency', 'Status', 'County', 'Campaign', 'Date']);

            foreach ($donations as $donation) {
                fputcsv($handle, [
                    $donation->id,
                    $donation->reference,
                    $donation->donor_name,
                    $donation->donor_email,
                    $donation->amount,
                    $donation->currency,
                    $donation->status,
                    $donation->county?->name ?? 'N/A',
                    $donation->campaign?->title ?? 'General',
                    $donation->created_at->format('Y-m-d H:i:s'),
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            return $content;
        } catch (\Exception $e) {
            Log::error('Report generation failed', ['error' => $e->getMessage()]);
            throw DonationException::reportGenerationFailed($e->getMessage());
        }
    }

    /**
     * Resume a pending donation payment
     */
    public function resumeDonationPayment(Donation $donation): array
    {
        if ($donation->status === 'paid') {
            throw DonationException::alreadyPaid($donation->id);
        }

        try {
            // Initiate a new payment session
            $paymentData = [
                'amount' => $donation->amount,
                'currency' => $donation->currency,
                'payment_method' => 'pesapal', // Default
                'description' => "Donation Reference: {$donation->reference}",
                'reference' => $donation->reference . '_' . time(), // Unique reference for this attempt
                'county' => $donation->county?->name,
                'payable_type' => 'App\\Models\\Donation',
                'payable_id' => $donation->id,
                'first_name' => explode(' ', $donation->donor_name)[0] ?? 'Donor',
                'last_name' => explode(' ', $donation->donor_name)[1] ?? 'Labs',
                'email' => $donation->donor_email,
                'phone' => $donation->donor_phone,
            ];

            // For guests, we can pass a dummy user or just have PaymentService modified to handle it
            // However, $paymentService->processPayment expects Authenticatable.
            // We'll use the donation's user if it exists, otherwise a "Guest" placeholder user
            $actor = $donation->user ?? User::where('email', 'admin@dadisilab.com')->first();

            return $this->paymentService->processPayment($actor, $paymentData);
        } catch (\Exception $e) {
            Log::error('Failed to resume donation payment', [
                'donation_id' => $donation->id,
                'error' => $e->getMessage()
            ]);
            throw DonationException::creationFailed('Could not resume payment: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a donation by reference (for guests)
     */
    public function cancelDonation(string $reference): bool
    {
        $donation = $this->getDonationByReference($reference);

        if ($donation->status !== 'pending') {
            throw DonationException::onlyPendingCanBeCancelled();
        }

        try {
            return DB::transaction(function () use ($donation) {
                AuditLog::create([
                    'action' => 'guest_cancelled_donation',
                    'model_type' => Donation::class,
                    'model_id' => $donation->id,
                    'old_values' => ['status' => $donation->status],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);

                return $donation->update(['status' => 'cancelled']);
            });
        } catch (\Exception $e) {
            Log::error('Guest donation cancellation failed', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get counties list for forms
     */
    public function getCounties(): \Illuminate\Database\Eloquent\Collection
    {
        return County::select('id', 'name')->orderBy('name')->get();
    }
}
