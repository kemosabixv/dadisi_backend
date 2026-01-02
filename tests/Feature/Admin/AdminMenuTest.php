<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $regularUser;

    protected $shouldSeedRoles = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::where('name', 'super_admin')->where('guard_name', 'api')->first();
        $this->superAdmin->assignRole($role);

        $this->regularUser = User::factory()->create();
        $this->regularUser->assignRole('member');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_menu_requires_authentication()
    {
        $response = $this->getJson('/api/admin/menu');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_can_access_menu()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/menu');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'title',
                        'path',
                    ],
                ],
            ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function super_admin_gets_all_menu_items()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/menu');

        $response->assertStatus(200);
        $menuItems = $response->json('data');

        // Should have menu items
        $this->assertGreaterThan(0, count($menuItems));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_gets_filtered_menu()
    {
        $response = $this->actingAs($this->regularUser)
            ->getJson('/api/admin/menu');

        $response->assertStatus(403);
        // Regular member is blocked by middleware, so we don't check for empty data.
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function menu_items_have_required_structure()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/menu');

        $response->assertStatus(200);
        $menuItems = $response->json('data');

        $this->assertNotEmpty($menuItems);
        foreach ($menuItems as $item) {
            $this->assertIsArray($item);
            $this->assertArrayHasKey('title', $item);
            $this->assertArrayHasKey('path', $item);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function menu_respects_role_permissions()
    {
        // Create a user with content_editor role
        $editorUser = User::factory()->create();
        $role = \Spatie\Permission\Models\Role::where('name', 'content_editor')->where('guard_name', 'api')->first();
        $editorUser->assignRole($role);

        $response = $this->actingAs($editorUser, 'sanctum')
            ->getJson('/api/admin/menu');

        $response->assertStatus(200);
        $menuItems = $response->json('data');

        // Content editor should only see dashboard and blog items
        $titles = array_column($menuItems, 'title');
        $this->assertContains('Dashboard', $titles);
        $this->assertContains('Blog', $titles);
        $this->assertNotContains('Users', $titles);
        $this->assertNotContains('Roles & Permissions', $titles);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_menu_response_is_json()
    {
        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/admin/menu');

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'data']);
    }
}
