<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class AdminMenuTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('super_admin');

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
                        'key',
                        'label',
                        'href',
                    ]
                ]
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

        $response->assertStatus(200);
        $menuItems = $response->json('data');

        // Regular member should see no admin menu items
        $this->assertEmpty($menuItems);
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
            $this->assertArrayHasKey('key', $item);
            $this->assertArrayHasKey('label', $item);
            $this->assertArrayHasKey('href', $item);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function menu_respects_role_permissions()
    {
        // Create a user with content_editor role
        $editorUser = User::factory()->create();
        $editorUser->assignRole('content_editor');

        $response = $this->actingAs($editorUser)
            ->getJson('/api/admin/menu');

        $response->assertStatus(200);
        $menuItems = $response->json('data');

        // Content editor should only see overview and blog items
        $keys = array_column($menuItems, 'key');
        $this->assertContains('overview', $keys);
        $this->assertContains('blog', $keys);
        $this->assertNotContains('user_management', $keys);
        $this->assertNotContains('roles_permissions', $keys);
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
