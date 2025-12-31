<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UsersManagementTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $ts;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and permissions first
        $this->seed();

        // create an admin user and give super_admin role (seeded in TestCase)
        $this->ts = time();
        $this->admin = User::create([
            'username' => 'adminuser_' . $this->ts,
            'email' => 'admin+' . $this->ts . '@example.com',
            'password' => 'password123',
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_admin_can_list_users()
    {
        $other = User::create(['username' => 'jane_' . $this->ts, 'email' => 'jane+' . $this->ts . '@example.com', 'password' => 'secret']);

        $resp = $this->actingAs($this->admin)->getJson('/api/admin/users');

        $resp->assertStatus(200)
            ->assertJsonStructure(['success','data'])
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_show_user()
    {
        $user = User::create(['username' => 'showme_' . $this->ts, 'email' => 'show+' . $this->ts . '@example.com', 'password' => 'secret']);

        $resp = $this->actingAs($this->admin)->getJson('/api/admin/users/' . $user->id);

        $resp->assertStatus(200)
            ->assertJsonPath('data.id', $user->id)
            ->assertJson(['success' => true]);
    }

    public function test_admin_can_update_user()
    {
        $user = User::create(['username' => 'updateme_' . $this->ts, 'email' => 'old+' . $this->ts . '@example.com', 'password' => 'secret']);

        $resp = $this->actingAs($this->admin)->putJson('/api/admin/users/' . $user->id, [
            'email' => 'new+' . $this->ts . '@example.com'
        ]);

        $resp->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.email', 'new+' . $this->ts . '@example.com');
    }

    public function test_admin_can_soft_delete_and_restore_user()
    {
        $user = User::create(['username' => 'deleteme_' . $this->ts, 'email' => 'del+' . $this->ts . '@example.com', 'password' => 'secret']);

        $resp = $this->actingAs($this->admin)->deleteJson('/api/admin/users/' . $user->id);
        $resp->assertStatus(200)->assertJson(['success' => true]);

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        // restore
        $resp2 = $this->actingAs($this->admin)->postJson('/api/admin/users/' . $user->id . '/restore');
        $resp2->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_assign_and_bulk_assign_roles()
    {
        $user = User::create(['username' => 'roleuser_' . $this->ts, 'email' => 'role+' . $this->ts . '@example.com', 'password' => 'secret']);

        $resp = $this->actingAs($this->admin)->postJson('/api/admin/users/' . $user->id . '/assign-role', [
            'role' => 'admin'
        ]);

        $resp->assertStatus(200)->assertJson(['success' => true]);

        // bulk assign
        $u1 = User::create(['username' => 'u1_' . $this->ts, 'email' => 'u1+' . $this->ts . '@example.com', 'password' => 'secret']);
        $u2 = User::create(['username' => 'u2_' . $this->ts, 'email' => 'u2+' . $this->ts . '@example.com', 'password' => 'secret']);

        $bulk = $this->actingAs($this->admin)->postJson('/api/admin/users/bulk/assign-role', [
            'user_ids' => [$u1->id, $u2->id],
            'role' => 'member'
        ]);

        $bulk->assertStatus(200)->assertJson(['success' => true]);
    }
}
