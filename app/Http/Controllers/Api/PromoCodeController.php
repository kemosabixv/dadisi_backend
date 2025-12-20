<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PromoCodeResource;
use App\Models\Event;
use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PromoCodeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Validate Promo Code
     * 
     * @group Promo Codes
     */
    public function validateCode(Request $request, Event $event)
    {
        $validated = $request->validate([
            'code' => 'required|string',
            'ticket_id' => 'required|exists:tickets,id',
        ]);

        $promo = PromoCode::where('code', $validated['code'])
            ->where(function ($q) use ($event) {
                $q->where('event_id', $event->id)
                  ->orWhereNull('event_id');
            })
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now());
            })
            ->first();

        if (!$promo) {
            return response()->json(['message' => 'Invalid or expired promo code.'], Response::HTTP_NOT_FOUND);
        }

        if ($promo->usage_limit && $promo->used_count >= $promo->usage_limit) {
            return response()->json(['message' => 'Promo code usage limit reached.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return (new PromoCodeResource($promo))->additional([
            'success' => true,
        ]);
    }

    /**
     * Admin: List all promo codes
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PromoCode::class);
        
        $query = PromoCode::with('event');
        
        if ($request->has('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        return PromoCodeResource::collection($query->latest()->paginate());
    }

    /**
     * Admin: Create promo code
     */
    public function store(Request $request)
    {
        $this->authorize('create', PromoCode::class);

        $validated = $request->validate([
            'event_id' => 'nullable|exists:events,id',
            'code' => 'required|string|unique:promo_codes,code',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
        ]);

        $promo = PromoCode::create($validated);

        return new PromoCodeResource($promo);
    }
}
