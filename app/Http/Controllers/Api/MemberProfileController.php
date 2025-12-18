<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\County;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class MemberProfileController extends Controller
{
    /**
     * List all member profiles
     *
     * Retrieves a paginated list of all member profiles.
     * RESTRICTED: Only accessible by users with 'super_admin' or 'admin' roles.
     * Supports filtering by county, membership type, and search by name/email.
     *
     * @group Member Profiles
     * @groupDescription Endpoints for managing user profiles, including personal details, contact info, and preferences. Users can manage their own profiles, while admins have broader access.
     * @authenticated
     * @header Authorization Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
     *
     * @queryParam county_id integer Filter by county ID. Example: 1
     * @queryParam membership_type string Filter by membership type name. Example: premium
     * @queryParam search string Search by user name or email. Example: john
     * @queryParam page integer Page number for pagination. Example: 1
     *
     * @response scenario="Admin Access" {
     *   "success": true,
     *   "data": {
     *     "current_page": 1,
     *     "data": [
     *       {
     *         "id": 1,
     *         "user": {"name": "John Smith", "email": "john@example.com"},
     *         "county": {"name": "Nairobi"},
     *         "first_name": "John",
     *         "last_name": "Smith",
     *         "terms_accepted": true,
     *         "created_at": "2024-01-01T12:00:00Z"
     *       }
     *     ],
     *     "total": 5,
     *     "per_page": 20
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized to view all profiles"
     * }
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        // Only admins can view all profiles
        if (!$user->hasRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view all profiles',
            ], 403);
        }

        $query = MemberProfile::with([
            'user:id,username,email,phone,email_verified_at,profile_picture_path',  
            'county:id,name',
            'subscriptionPlan:id,slug,name,price,description'
        ]);

        // Filter by county if provided
        if ($request->has('county_id')) {
            $query->byCounty($request->county_id);
        }

        // Filter by membership type (plan name)
        if ($request->has('membership_type')) {
            $query->whereHas('subscriptionPlan', function($q) use ($request) {
                $q->where('name', $request->membership_type);
            });
        }

        // Search by name/email
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->whereHas('user', function($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('email', 'like', "%{$searchTerm}%");
            });
        }

        $profiles = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\MemberProfileResource::collection($profiles),
        ]);
    }

    /**
     * Get current user profile
     *
     * Retrieves the profile associated with the currently authenticated user.
     * This is the primary endpoint for fetching user details for the dashboard/profile page.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe", "email": "john@example.com"},
     *     "county": {"name": "Nairobi", "id": 47},
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "gender": "male",
     *     "terms_accepted": true,
     *     "subscription_plan": {"name": "Free", "price": 0}
     *   }
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Profile not found. Please create a profile first."
     * }
     */
    public function me(): JsonResponse
    {
        $profile = auth()->user()->memberProfile;

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found. Please create a profile first.',
            ], 404);
        }

        // WARNING: This endpoint is NOT to be used for authorization or admin checks.
        // Use /api/auth/me instead.
        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\MemberProfileResource::make($profile->load([
                'user:id,username,email,phone,email_verified_at,profile_picture_path',
                'county:id,name',
                'subscriptionPlan:id,slug,name,price,description'
            ])),
        ]);
    }

    /**
     * Create/Update current user profile
     *
     * Creates a new profile for the authenticated user if one doesn't exist, or updates the existing one.
     * This ensures the user has a linked profile record with necessary details like county and contact info.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @bodyParam county_id integer required County ID. Must correspond to a valid county. Example: 47
     * @bodyParam first_name string optional First name. Max 100 chars. Auto-filled from user name if empty. Example: John
     * @bodyParam last_name string optional Last name. Max 100 chars. Auto-filled if empty. Example: Doe
     * @bodyParam phone string optional Phone number. Max 30 characters. Example: +254712345678
     * @bodyParam gender string optional Gender identity. One of: male, female, other. Example: male
     * @bodyParam date_of_birth date optional Date of birth. Must be a past date. Example: 1990-01-15
     * @bodyParam occupation string optional Professional occupation. Max 255 chars. Example: Software Developer
     * @bodyParam membership_type string optional Desired membership plan (e.g., 'free', 'premium'). Example: free
     * @bodyParam emergency_contact_name string optional Name of emergency contact. Example: Jane Doe
     * @bodyParam emergency_contact_phone string optional Phone of emergency contact. Example: +254798765432
     * @bodyParam terms_accepted boolean required Confirmation of T&C acceptance. Example: true
     * @bodyParam marketing_consent boolean optional Consent to receive marketing materials. Example: false
     * @bodyParam interests array optional List of user interests/tags. Example: ["technology", "community"]
     * @bodyParam bio string optional Short biography. Max 1000 chars. Example: Enthusiastic learner.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile updated successfully",
     *   "data": {
     *     "id": 1,
     *     "first_name": "John",
     *     "county_id": 47
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The county_id field is required.",
     *   "errors": {"county_id": ["The county_id field is required."]}
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'county_id' => ['required', 'integer', Rule::exists('counties', 'id')],
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'gender' => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date|before:today',
            'occupation' => 'nullable|string|max:255',
            'membership_type' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:15',
            'terms_accepted' => 'required|boolean',
            'marketing_consent' => 'sometimes|boolean',
            'interests' => 'nullable|array',
            'bio' => 'nullable|string|max:1000',
        ]);

        $profile = $user->memberProfile;

        if (!$profile) {
            // Map incoming keys to DB columns
            $createData = $validated;
            $createData['user_id'] = $user->id;

            // Map phone -> phone_number
            if (isset($createData['phone'])) {
                $createData['phone_number'] = $createData['phone'];
                unset($createData['phone']);
            }

            // If first/last name not provided, try to parse from user name
            if (empty($createData['first_name']) && empty($createData['last_name'])) {
                $nameParts = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                $createData['first_name'] = $nameParts[0] ?? '';
                $createData['last_name'] = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
            }

            // Handle membership_type: if a string, try to resolve to subscription plan id
            if (!empty($createData['membership_type']) && !is_numeric($createData['membership_type'])) {
                $plan = \App\Models\SubscriptionPlan::where('name', $createData['membership_type'])->first();
                $createData['membership_type'] = $plan ? $plan->id : null;
            }

            $profile = MemberProfile::create($createData);
        } else {
            // Map updates similarly
            if (isset($validated['phone'])) {
                $validated['phone_number'] = $validated['phone'];
                unset($validated['phone']);
            }

            // If first/last not provided, try parse from user name only when empty
            if (empty($validated['first_name']) && empty($validated['last_name'])) {
                $nameParts = preg_split('/\s+/', trim($user->name ?? ''), -1, PREG_SPLIT_NO_EMPTY);
                $validated['first_name'] = $nameParts[0] ?? '';
                $validated['last_name'] = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
            }

            if (!empty($validated['membership_type']) && !is_numeric($validated['membership_type'])) {
                $plan = \App\Models\SubscriptionPlan::where('name', $validated['membership_type'])->first();
                $validated['membership_type'] = $plan ? $plan->id : null;
            }

            $profile->update($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => \App\Http\Resources\MemberProfileResource::make($profile->load([
                'user:id,username,email,phone,email_verified_at,profile_picture_path',
                'county:id,name',
                'subscriptionPlan:id,slug,name,price,description'
            ])),
        ]);
    }

    /**
     * @group Member Profiles
     * @authenticated
     * @description Delete a profile. Only admins can delete profiles, and cannot delete their own profile.
     *
     * Required permissions: manage_users (Super Admin, Admin roles)
     *
     * @urlParam id int required The profile ID to delete. Example: 123
     *
     * @response {
     *   "success": true,
     *   "message": "Profile deleted successfully"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Cannot delete profile"
     * }
     */
    /**
     * Delete a profile
     *
     * Permanently deletes a member profile.
     * RESTRICTED: Only admins can delete profiles, but they cannot delete their own profile via this endpoint.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @urlParam id integer required The unique ID of the profile to delete. Example: 123
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile deleted successfully"
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Cannot delete profile"
     * }
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user->hasRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete profile',
            ], 403);
        }

        $profile = MemberProfile::findOrFail($id);

        // Prevent admins from deleting their own profile
        if ($profile->user_id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete profile',
            ], 403);
        }

        // Perform a hard delete for admin-initiated removals (tests expect hard delete)
        $profile->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Profile deleted successfully',
        ]);
    }
    /**
     * Get specific profile details
     *
     * Retrieves details for a specific profile by ID.
     * - Users can always view their own profile (if ID matches or if ID is omitted, though `me` endpoint is preferred for that).
     * - Admins can view any user's profile.
     * - Access is denied if a regular user tries to view another user's profile.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @urlParam id integer optional The ID of the profile to view. If omitted, defaults to the authenticated user's profile. Example: 123
     *
     * @response 200 {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe", "email": "john@example.com"},
     *     "county": {"name": "Nairobi"},
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "gender": "male",
     *     "terms_accepted": true
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized to view this profile"
     * }
     *
     * @response 404 {
     *   "success": false,
     *   "message": "Profile not found"
     * }
     */
    public function show(Request $request, $id = null): JsonResponse
    {
        $user = auth()->user();

        // Check if requesting own profile
        if (!$id) {
            $profile = $user->memberProfile;
            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found. Please create a profile first.',
                ], 404);
            }
        } else {
            // Check if requesting own profile or has admin role
            $profile = MemberProfile::with([
                'user:id,username,email,phone,email_verified_at,profile_picture_path',  
                'county:id,name',
                'subscriptionPlan:id,slug,name,price,description'
            ])->findOrFail($id);

            // If not the profile owner and not admin, deny access
            if ($profile->user_id !== $user->id && !$user->hasRole(['super_admin', 'admin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this profile',
                ], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\MemberProfileResource::make($profile->load([
                'user:id,username,email,phone,email_verified_at,profile_picture_path',
                'county:id,name',
                'subscriptionPlan:id,slug,name,price,description'
            ])),
        ]);
    }

    /**
     * @group Member Profiles
     * @authenticated
     * @description Create or update authenticated user's profile. All users can manage their own profile.
     *
     * @bodyParam county_id integer required County ID (NGO compliance requirement). Must exist in counties table. Example: 1
     * @bodyParam phone string optional Phone number. Max 30 characters. Example: +254712345678
     * @bodyParam gender string optional Gender. Allowed: male, female, other. Example: male
     * @bodyParam date_of_birth date optional Date of birth. Must be in past. Example: 1990-01-15
     * @bodyParam occupation string optional Occupation/profession. Max 255 chars. Example: Software Developer
     * @bodyParam membership_type string optional Membership level. Allowed: free, premium, student, corporate. Example: free
     * @bodyParam emergency_contact_name string optional Emergency contact name. Example: Jane Doe
     * @bodyParam emergency_contact_phone string optional Emergency contact phone. Example: +254798765432
     * @bodyParam terms_accepted boolean required Must accept terms to create profile. Example: true
     * @bodyParam marketing_consent boolean optional Marketing consent. Example: false
     * @bodyParam interests array optional User interests as array. Example: ["technology", "community"]
     * @bodyParam bio string optional User biography. Max 1000 chars. Example: Passionate about community development
     *
     * @response {
     *   "success": true,
     *   "message": "Profile updated successfully",
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe", "email": "john@example.com"},
     *     "county": {"name": "Nairobi"},
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "gender": "male",
     *     "terms_accepted": true
     *   }
     * }
     *
     * @response 422 {
     *   "message": "The county_id field is required.",
     *   "errors": {
     *     "county_id": ["The county_id field is required."]
     *   }
     * }
     */


    /**
     * Update specific profile
     *
     * Updates an existing profile by ID.
     * - Regular users can only update their own profile.
     * - Admins ('super_admin', 'admin') can update any profile.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @urlParam id integer required The ID of the profile to update. Example: 1
     * @bodyParam county_id integer optional County ID. Example: 1
     * @bodyParam phone string optional Phone number. Example: +254712345678
     * @bodyParam gender string optional Gender. Example: female
     * @bodyParam occupation string optional New occupation. Example: Project Manager
     * @bodyParam bio string optional Biography. Example: Updated bio text.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile updated successfully",
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe"},
     *     "county": {"name": "Nairobi"},
     *     "occupation": "Project Manager"
     *   }
     * }
     *
     * @response 403 {
     *   "success": false,
     *   "message": "Unauthorized to update this profile"
     * }
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = auth()->user();
        $profile = MemberProfile::findOrFail($id);

        // Only allow updating own profile or admin managing users
        if ($profile->user_id !== $user->id && !$user->hasRole(['super_admin', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this profile',
            ], 403);
        }

        $validated = $request->validate([
            'county_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('counties', 'id')
            ],
            'phone_number' => 'nullable|string|max:15',
            'gender' => 'nullable|in:male,female',
            'date_of_birth' => 'nullable|date|before:today',
            'sub_county' => 'nullable|string|max:50',
            'ward' => 'nullable|string|max:50',
            'occupation' => 'nullable|string|max:255',
            'membership_type' => [
                'nullable',
                'integer',
                Rule::exists('subscription_plans', 'id')
            ],
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_phone' => 'nullable|string|max:15',
            'terms_accepted' => 'sometimes|boolean',
            'marketing_consent' => 'sometimes|boolean',
            'interests' => 'nullable|array',
            'bio' => 'nullable|string|max:1000',
        ]);

        $profile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => \App\Http\Resources\MemberProfileResource::make($profile->load([
                'user:id,username,email,phone,email_verified_at,profile_picture_path',
                'county:id,name',
                'subscriptionPlan:id,slug,name,price,description'
            ])),
        ]);
    }


    /**
     * List all counties
     *
     * Retrieves a list of all available counties, sorted alphabetically.
     * Helper endpoint for populating dropdowns in profile creation/edit forms.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @response 200 {
     *   "success": true,
     *   "data": [
     *     {"id": 35, "name": "Nairobi"},
     *     {"id": 47, "name": "Mombasa"},
     *     {"id": 1, "name": "Kisumu"}
     *   ]
     * }
     */
    public function getCounties(): JsonResponse
    {
        $counties = County::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $counties,
        ]);
    }

    /**
     * Upload profile picture
     *
     * Uploads and updates the authenticated user's profile picture.
     * Replaces any existing picture.
     *
     * @group Member Profiles
     * @authenticated
     *
     * @bodyParam profile_picture file required The image file (jpeg, png, jpg, gif). Max 2MB.
     *
     * @response 200 {
     *   "success": true,
     *   "message": "Profile picture updated successfully",
     *   "data": {
     *     "profile_picture_url": "http://example.com/storage/profile-pictures/filename.jpg"
     *   }
     * }
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        $request->validate([
            'profile_picture' => ['required', 'image', 'max:5120'],
        ]);

        $user = auth()->user();

        if ($request->hasFile('profile_picture')) {
            // delete old image if exists
            if ($user->profile_picture_path) {
                Storage::disk('public')->delete($user->profile_picture_path);
            }

            // store new image
            $path = $request->file('profile_picture')->store('profile-pictures', 'public');

            // update user
            $user->profile_picture_path = $path;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture_url' => $user->profile_picture_url,
                    'user' => $user->load('memberProfile') // Return updated user for frontend state
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No file uploaded',
        ], 400);
    }
}
