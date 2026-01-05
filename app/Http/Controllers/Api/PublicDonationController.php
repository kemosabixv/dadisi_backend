<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DonationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePublicDonationRequest;
use App\Http\Resources\DonationResource;
use App\Services\Contracts\DonationServiceContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @group Donations - Public
 */
class PublicDonationController extends Controller
{
    protected DonationServiceContract $donationService;

    public function __construct(DonationServiceContract $donationService)
    {
        $this->donationService = $donationService;
        $this->middleware('auth:sanctum')->only(['index', 'destroy']);
    }

    /**
     * List User's Donations
     * 
     * @group Donations - Public
     * @authenticated
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = min((int) $request->input('per_page', 15), 50);

            $donations = $this->donationService->listDonations(
                ['user_id' => $user->getAuthIdentifier()],
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => DonationResource::collection($donations),
                'pagination' => [
                    'total' => $donations->total(),
                    'per_page' => $donations->perPage(),
                    'current_page' => $donations->currentPage(),
                    'last_page' => $donations->lastPage(),
                ],
                'message' => 'Your donations retrieved successfully'
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController index failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve donations'], 500);
        }
    }

    /**
     * Create General Donation
     * 
     * @group Donations - Public
     */
    public function store(StorePublicDonationRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = $request->user('sanctum');
            
            $donationData = [
                'donor_name' => $validated['first_name'] . ' ' . $validated['last_name'],
                'donor_email' => $validated['email'],
                'donor_phone' => $validated['phone_number'] ?? null,
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'KES',
                'county_id' => $validated['county_id'] ?? ($user?->profile?->county_id),
                'notes' => $validated['message'] ?? null,
            ];

            $donation = $this->donationService->createDonation($user, $donationData);
            $redirectUrl = config('app.frontend_url') . '/donations/checkout/' . $donation->reference;

            return response()->json([
                'success' => true,
                'message' => 'Donation initiated',
                'data' => [
                    'donation_id' => $donation->id,
                    'reference' => $donation->reference,
                    'amount' => $donation->amount,
                    'currency' => $donation->currency,
                    'redirect_url' => $redirectUrl,
                ],
            ], 201);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController store failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Internal server error'], 500);
        }
    }

    /**
     * Get Donation by Reference
     * 
     * @group Donations - Public
     */
    public function show(string $reference): JsonResponse
    {
        try {
            $donation = $this->donationService->getDonationByReference($reference);

            return response()->json([
                'success' => true,
                'data' => new DonationResource($donation),
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController show failed', ['error' => $e->getMessage(), 'reference' => $reference]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve donation'], 500);
        }
    }

    /**
     * View/Download Donation Receipt
     * 
     * @group Donations - Public
     */
    public function receipt(string $reference)
    {
        try {
            $donation = $this->donationService->getDonationByReference($reference);

            if (!$donation->isPaid()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Receipt only available for paid donations'
                ], 400);
            }

            return view('donations.receipt', compact('donation'));
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController receipt failed', ['error' => $e->getMessage(), 'reference' => $reference]);
            return response()->json(['success' => false, 'message' => 'Failed to generate receipt'], 500);
        }
    }

    /**
     * Cancel Pending Donation
     * 
     * @group Donations - Public
     * @authenticated
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $donation = $this->donationService->getDonation((string) $id);
            $this->donationService->deleteDonation($request->user(), $donation);

            return response()->json([
                'success' => true,
                'message' => 'Donation cancelled successfully',
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController destroy failed', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel donation'], 500);
        }
    }

    /**
     * Resume Donation Payment
     * 
     * @group Donations - Public
     */
    public function resume(string $reference): JsonResponse
    {
        try {
            $donation = $this->donationService->getDonationByReference($reference);
            $result = $this->donationService->resumeDonationPayment($donation);

            return response()->json([
                'success' => true,
                'message' => 'Donation payment resumed',
                'data' => [
                    'redirect_url' => $result['redirect_url'],
                    'transaction_id' => $result['transaction_id'],
                ],
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController resume failed', ['error' => $e->getMessage(), 'reference' => $reference]);
            return response()->json(['success' => false, 'message' => 'Failed to resume payment'], 500);
        }
    }

    /**
     * Cancel Donation (by reference)
     * 
     * @group Donations - Public
     */
    public function cancel(string $reference): JsonResponse
    {
        try {
            $success = $this->donationService->cancelDonation($reference);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to cancel donation or donation not in cancelable state'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'Donation cancelled successfully',
            ]);
        } catch (DonationException $e) {
            return $e->render();
        } catch (\Exception $e) {
            Log::error('PublicDonationController cancel failed', ['error' => $e->getMessage(), 'reference' => $reference]);
            return response()->json(['success' => false, 'message' => 'Failed to cancel donation'], 500);
        }
    }
}
