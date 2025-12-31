<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Exceptions\UserException;
use App\Services\Contracts\UserServiceContract;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MemberProfileController extends Controller
{
    public function __construct(private UserServiceContract $userService)
    {
    }
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
     *         "user": {"id": 1, "username": "superadmin", "email": "superadmin@dadisilab.com"},
     *         "county": {"id": 1, "name": "Nairobi"},
     *         "first_name": "Super",
     *         "last_name": "Admin",
     *         "terms_accepted": true,
     *         "created_at": "2025-01-01T12:00:00Z"
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
        try {
            $filters = [
                'county_id' => $request->input('county_id'),
                'membership_type' => $request->input('membership_type'),
                'search' => $request->input('search'),
                'page' => $request->input('page', 1),
            ];
            $result = $this->userService->listMemberProfiles($filters);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve member profiles', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve member profiles'], 500);
        }
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
     *     "id": 2,
     *     "user": {"id": 2, "username": "jane_doe", "email": "jane.doe@example.com"},
     *     "county": {"id": 1, "name": "Nairobi"},
     *     "first_name": "Jane",
     *     "last_name": "Doe",
     *     "gender": "female",
     *     "terms_accepted": true,
     *     "subscription_plan": {"name": "Basic", "price": 0}
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
        try {
            $profile = $this->userService->getCurrentUserProfile();
            return response()->json(['success' => true, 'data' => $profile]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve current user profile', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Profile not found. Please create a profile first.'], 404);
        }
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
     *     "id": 2,
     *     "first_name": "Jane",
     *     "last_name": "Doe",
     *     "county_id": 1
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
        
        try {
            $profile = $this->userService->createOrUpdateMemberProfile($validated);
            return response()->json(['success' => true, 'message' => 'Profile updated successfully', 'data' => $profile]);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 400);
        } catch (\Exception $e) {
            Log::error('Failed to update profile', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update profile'], 500);
        }
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
        try {
            $this->userService->deleteMemberProfile($id);
            return response()->json(['success' => true, 'message' => 'Profile deleted successfully']);
        } catch (UserException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getCode() ?: 403);
        } catch (\Exception $e) {
            Log::error('Failed to delete profile', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Cannot delete profile'], 403);
        }
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
        try {
            $profile = $this->userService->getMemberProfile($id);
            return response()->json(['success' => true, 'data' => $profile]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve profile', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Profile not found'], 404);
        }
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
        try {
            $validated = $request->validate([
                'county_id' => ['sometimes', 'required', 'integer', Rule::exists('counties', 'id')],
                'phone_number' => 'nullable|string|max:15',
                'gender' => 'nullable|in:male,female',
                'date_of_birth' => 'nullable|date|before:today',
                'sub_county' => 'nullable|string|max:50',
                'ward' => 'nullable|string|max:50',
                'occupation' => 'nullable|string|max:255',
                'membership_type' => ['nullable', 'integer', Rule::exists('subscription_plans', 'id')],
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:15',
                'terms_accepted' => 'sometimes|boolean',
                'marketing_consent' => 'sometimes|boolean',
                'interests' => 'nullable|array',
                'bio' => 'nullable|string|max:1000',
            ]);
            $profile = $this->userService->updateMemberProfile($id, $validated);
            return response()->json(['success' => true, 'message' => 'Profile updated successfully', 'data' => $profile]);
        } catch (\Exception $e) {
            Log::error('Failed to update profile', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Unauthorized to update this profile'], 403);
        }
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
        try {
            $counties = $this->userService->listCounties();
            return response()->json(['success' => true, 'data' => $counties]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve counties', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve counties'], 500);
        }
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
     *     "profile_picture_url": "https://api.dadisilab.com/storage/profile-pictures/jane_doe_avatar.jpg"
     *   }
     * }
     */
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        try {
            $request->validate(['profile_picture' => ['required', 'image', 'max:5120']]);
            $result = $this->userService->uploadProfilePicture($request->user(), $request->file('profile_picture'));
            return response()->json(['success' => true, 'message' => 'Profile picture updated successfully', 'data' => $result]);
        } catch (\Exception $e) {
            Log::error('Failed to upload profile picture', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to upload profile picture'], 500);
        }
    }
}
