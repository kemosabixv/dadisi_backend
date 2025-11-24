<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MemberProfile;
use App\Models\County;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class MemberProfileController extends Controller
{
    /**
     * @group Member Profiles
     * @authenticated
     * @header Authorization Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
     * @description List all member profiles with filtering options (Admin roles only)
     *
     * Required roles: super_admin, admin
     *
     * @queryParam county_id Filter by county ID. Example: 1
     * @queryParam membership_type Filter by membership type. Example: premium
     * @queryParam search Search by user name or email. Example: john
     *
     * @response scenario="Admin Access" {
     *   "success": true,
     *   "data": {
     *     "data": [
     *       {
     *         "id": 1,
     *         "user": {"name": "John Smith", "email": "john@example.com"},
     *         "county": {"name": "Nairobi"},
     *         "first_name": "John",
     *         "last_name": "Smith",
     *         "terms_accepted": true
     *       }
     *     ],
     *     "current_page": 1,
     *     "total": 5
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

        $query = MemberProfile::with(['user', 'county']);

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
            'data' => $profiles,
        ]);
    }

    /**
     * @group Member Profiles
     * @authenticated
     * @description Get the authenticated user's own profile. This is a convenient endpoint that always returns the current user's profile.
     *
     * @response {
     *   "success": true,
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe", "email": "john@example.com"},
     *     "county": {"name": "Nairobi"},
     *     "first_name": "John",
     *     "last_name": "Doe",
     *     "gender": "male",
     *     "terms_accepted": true,
     *     "subscription_plan": {"name": "Free", "price": null}
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

        return response()->json([
            'success' => true,
            'data' => $profile->load(['user', 'county', 'subscriptionPlan']),
        ]);
    }

    /**
     * @group Member Profiles
     * @authenticated
     * @description Create or update authenticated user's profile. All users can manage their own profile.
     *
     * @bodyParam county_id integer required County ID. Example: 1
     * @bodyParam first_name string optional First name. Max 100 chars. Example: John
     * @bodyParam last_name string optional Last name. Max 100 chars. Example: Doe
     * @bodyParam phone string optional Phone number. Max 30 characters. Example: +254712345678
     * @bodyParam gender string optional Gender. One of: male, female, other. Example: male
     * @bodyParam date_of_birth date optional Date of birth. Must be before today. Example: 1990-01-15
     * @bodyParam occupation string optional Occupation. Max 255 chars. Example: Software Developer
     * @bodyParam membership_type string optional Membership plan name. Example: free
     * @bodyParam emergency_contact_name string optional Example: Jane Doe
     * @bodyParam emergency_contact_phone string optional Example: +254798765432
     * @bodyParam terms_accepted boolean required Must accept terms to create profile. Example: true
     * @bodyParam marketing_consent boolean optional Example: false
     * @bodyParam interests array optional Example: ["technology","community"]
     * @bodyParam bio string optional Max 1000 chars. Example: Passionate about community development
     *
     * @response {
     *   "success": true,
     *   "message": "Profile updated successfully",
     *   "data": {"id": 1}
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
            'data' => $profile->load(['user', 'county', 'subscriptionPlan']),
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
     * @group Member Profiles
     * @authenticated
     * @description Get authenticated user's profile. All users can view their own profile. This is a convenient endpoint that always returns the current user's profile.
     *
     * For admins: Can view any user's profile (super_admin, admin roles).
     *
     * @urlParam id integer optional The profile ID to view. If not provided, returns authenticated user's profile. Example: 123
     *
     * @response {
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
     *   "message": "Profile not found. Please create a profile first."
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
            $profile = MemberProfile::with(['user', 'county'])->findOrFail($id);

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
            'data' => $profile->load(['user', 'county', 'subscriptionPlan']),
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
     * @group Member Profiles
     * @authenticated
     * @description Update an existing profile. Users can update their own profile. Admins can update any profile.
     *
     * Required roles for others: super_admin, admin
     *
     * @urlParam id required The profile ID to update.
     * @bodyParam county_id integer optional County ID. Must exist in counties table. Example: 1
     * @bodyParam phone string optional Phone number. Max 30 characters. Example: +254712345678
     * @bodyParam gender string optional Gender. Example: female
     * @bodyParam occupation string optional New occupation. Example: Project Manager
     *
     * @response {
     *   "success": true,
     *   "message": "Profile updated successfully",
     *   "data": {
     *     "id": 1,
     *     "user": {"name": "John Doe"},
     *     "county": {"name": "Nairobi"},
     *     "gender": "female",
     *     "occupation": "Project Manager"
     *   }
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
            'data' => $profile->load(['user', 'county', 'subscriptionPlan']),
        ]);
    }


    /**
     * @group Member Profiles
     * @authenticated
     * @description Get list of all available counties for profile forms.
     *
     * Returns counties sorted alphabetically by name for use in dropdowns.
     *
     * @response {
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
}
