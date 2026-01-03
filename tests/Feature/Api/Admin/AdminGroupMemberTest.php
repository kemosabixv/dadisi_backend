<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Group;
use App\Models\MemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminGroupMemberTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesPermissionsSeeder::class);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_superadmin_can_list_group_members()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $group = Group::factory()->create();
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            MemberProfile::factory()->create(['user_id' => $user->id]);
            $group->members()->attach($user->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        $response = $this->actingAs($superAdmin)
            ->getJson("/api/admin/groups/{$group->id}/members");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta',
            ]);
    }

    public function test_moderator_can_list_group_members()
    {
        $moderator = User::factory()->create();
        $moderator->assignRole(\Spatie\Permission\Models\Role::findByName('moderator', 'api'));

        $group = Group::factory()->create();
        $users = User::factory()->count(2)->create();

        foreach ($users as $user) {
            MemberProfile::factory()->create(['user_id' => $user->id]);
            $group->members()->attach($user->id, [
                'role' => 'member',
                'joined_at' => now(),
            ]);
        }

        $response = $this->actingAs($moderator, 'api')
            ->getJson("/api/admin/groups/{$group->id}/members");

        // Moderator now has manage_groups permission in the seeder
        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_superadmin_is_authorized_for_lab_space_update()
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole('super_admin');

        $labSpace = \App\Models\LabSpace::factory()->create();

        $response = $this->actingAs($superAdmin)
            ->putJson("/api/admin/spaces/{$labSpace->id}", [
                'name' => 'Updated Lab Name',
                'type' => 'dry_lab',
                'capacity' => 20,
            ]);

        $response->assertStatus(200);
    }
}
