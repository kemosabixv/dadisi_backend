<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\MemberProfile;
use App\Models\County;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class MemberProfileTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders to set up roles, permissions, and test data
        $this->seed();
    }

    /**
     * Test user can create and view their own profile
     */
    public function test_user_can_create_and_view_own_profile(): void
    {
        $user = User::factory()->create();
        $county = County::first();

        $profileData = [
            'county_id' => $county->id,
            'phone' => '+254712345678',
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'occupation' => 'Software Developer',
            'membership_type' => 'free',
            'emergency_contact_name' => 'John Doe',
            'emergency_contact_phone' => '+254798765432',
            'terms_accepted' => true,
            'marketing_consent' => false,
            'interests' => ['technology', 'community'],
            'bio' => 'Passionate about community development',
        ];

        // Authenticate user
        $this->actingAs($user, 'sanctum');

        // Create/update profile
        $response = $this->postJson('/api/member-profiles', $profileData);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);

        // Verify profile was created with first/last name parsing
        $profile = $user->fresh()->memberProfile;
        $this->assertNotNull($profile);
        $this->assertEquals($county->id, $profile->county_id);
        $this->assertEquals('male', $profile->gender);
        $this->assertEquals('Software Developer', $profile->occupation);
        $this->assertTrue($profile->terms_accepted);

        // Get own profile using show method
        $response = $this->getJson('/api/member-profiles/' . $profile->id);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ])
                ->assertJsonPath('data.user.name', $user->name)
                ->assertJsonPath('data.county.name', $county->name);
    }

    /**
     * Test admin can view all profiles
     */
    public function test_admin_can_view_all_profiles(): void
    {
        $admin = User::where('email', 'admin@dadisilab.com')->first();

        // Create some test profiles
        $user1 = User::factory()->create(['name' => 'John Smith']);
        $user2 = User::factory()->create(['name' => 'Jane Doe']);

        $county = County::first();

        // Create profiles for users
        MemberProfile::create([
            'user_id' => $user1->id,
            'county_id' => $county->id,
            'first_name' => 'John',
            'last_name' => 'Smith',
            'terms_accepted' => true,
        ]);

        MemberProfile::create([
            'user_id' => $user2->id,
            'county_id' => $county->id,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'terms_accepted' => true,
        ]);

        // Authenticate as admin
        $this->actingAs($admin, 'sanctum');

        // List all profiles (admin permission)
        $response = $this->getJson('/api/member-profiles');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        // Should have pagination data
        $response->assertJsonStructure([
            'success',
            'data' => [
                'data' => [
                    '*' => [
                        'id',
                        'user' => ['name', 'email'],
                        'county' => ['name'],
                        'first_name',
                        'last_name',
                    ]
                ],
                'current_page',
                'total'
            ]
        ]);
    }

    /**
     * Test regular user cannot view all profiles
     */
    public function test_regular_user_cannot_view_all_profiles(): void
    {
        $user = User::factory()->create();

        // Authenticate as regular user
        $this->actingAs($user, 'sanctum');

        // Try to list all profiles (should fail)
        $response = $this->getJson('/api/member-profiles');

        $response->assertStatus(403); // Forbidden
    }

    /**
     * Test profile validation
     */
    public function test_profile_validation(): void
    {
        $user = User::factory()->create();

        // Authenticate user
        $this->actingAs($user, 'sanctum');

        // Try to create profile without required county
        $response = $this->postJson('/api/member-profiles', [
            'phone' => '+254712345678',
            'terms_accepted' => true,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['county_id']);
    }

    /**
     * Test counties endpoint
     */
    public function test_can_get_counties_list(): void
    {
        $user = User::factory()->create();

        // Authenticate user
        $this->actingAs($user, 'sanctum');

        // Get counties
        $response = $this->getJson('/api/counties');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                ]);

        // Should return county data
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['id', 'name']
            ]
        ]);
    }

    /**
     * Test profile admin update
     */
    public function test_admin_can_update_other_profiles(): void
    {
        $admin = User::where('email', 'admin@dadisilab.com')->first();
        $user = User::factory()->create(['name' => 'Test User']);
        $county = County::first();

        // Create profile for user
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'county_id' => $county->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'terms_accepted' => true,
            'gender' => 'male',
        ]);

        // Authenticate as admin
        $this->actingAs($admin, 'sanctum');

        // Update the profile as admin
        $response = $this->putJson("/api/member-profiles/{$profile->id}", [
            'county_id' => $county->id,
            'gender' => 'female',
            'occupation' => 'Updated Profession',
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);

        // Verify update
        $profile->refresh();
        $this->assertEquals('female', $profile->gender);
        $this->assertEquals('Updated Profession', $profile->occupation);
    }

    /**
     * Test admin can delete profiles
     */
    public function test_admin_can_delete_profiles(): void
    {
        $admin = User::where('email', 'admin@dadisilab.com')->first();
        $user = User::factory()->create();
        $county = County::first();

        // Create profile for user
        $profile = MemberProfile::create([
            'user_id' => $user->id,
            'county_id' => $county->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'terms_accepted' => true,
        ]);

        // Authenticate as admin
        $this->actingAs($admin, 'sanctum');

        // Delete the profile
        $response = $this->deleteJson("/api/member-profiles/{$profile->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Profile deleted successfully'
                ]);

        // Verify deletion (hard delete, not soft delete)
        $this->assertDatabaseMissing('member_profiles', ['id' => $profile->id]);
    }
}
