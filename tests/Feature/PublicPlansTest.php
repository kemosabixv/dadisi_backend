<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\Plan;

class PublicPlansTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_public_index_returns_200_and_structure()
    {
        Plan::factory()->create([ 'name' => ['en' => 'Test Plan A'], 'slug' => 'test-a', 'is_active' => true ]);
        Plan::factory()->create([ 'name' => ['en' => 'Test Plan B'], 'slug' => 'test-b', 'is_active' => true ]);

        $response = $this->getJson('/api/plans');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertIsArray($response->json('data'));
        $item = $response->json('data')[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('pricing', $item);
        $this->assertArrayHasKey('promotions', $item);
        $this->assertArrayHasKey('features', $item);
    }

    #[Test]
    public function test_public_show_returns_200_and_structure()
    {
        $plan = Plan::factory()->create([ 'name' => ['en' => 'Show Plan'], 'slug' => 'show-plan', 'is_active' => true ]);

        $response = $this->getJson('/api/plans/' . $plan->id);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals($plan->id, $response->json('data.id'));
        $this->assertArrayHasKey('features', $response->json('data'));
    }
}
