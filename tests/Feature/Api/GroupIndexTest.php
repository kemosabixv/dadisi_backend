<?php

namespace Tests\Feature\Api;

use App\Models\County;
use App\Models\Group;
use App\Models\User;
use App\Models\ForumThread;
use App\Models\ForumCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GroupIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed some counties
        County::factory()->count(3)->create();
    }

    public function test_it_returns_paginated_groups_with_correct_counts()
    {
        $county = County::first();
        $group = Group::create([
            'name' => 'Nairobi Hub',
            'slug' => 'nairobi-hub',
            'description' => 'Test',
            'county_id' => $county->id,
            'is_active' => true,
        ]);

        $category = ForumCategory::create(['name' => 'General', 'slug' => 'general']);
        $user = User::factory()->create();

        // Create threads strictly linked to the group
        ForumThread::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Thread 1',
            'slug' => 'thread-1',
        ]);

        ForumThread::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'group_id' => $group->id,
            'title' => 'Thread 2',
            'slug' => 'thread-2',
        ]);

        $response = $this->getJson('/api/groups');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Nairobi Hub')
            ->assertJsonPath('data.0.thread_count', 2);
    }

    public function test_it_identifies_member_status_correctly()
    {
        $county = County::first();
        $group = Group::create([
            'name' => 'Nairobi Hub',
            'slug' => 'nairobi-hub',
            'description' => 'Test',
            'county_id' => $county->id,
            'is_active' => true,
        ]);

        $user = User::factory()->create();
        $group->members()->attach($user->id);

        // Authenticated request
        $response = $this->actingAs($user)->getJson('/api/groups');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.is_member', true);

        // Another user
        $otherUser = User::factory()->create();
        $response = $this->actingAs($otherUser)->getJson('/api/groups');
        $response->assertJsonPath('data.0.is_member', false);
    }

    public function test_it_sorts_by_thread_count()
    {
        $category = ForumCategory::create(['name' => 'General', 'slug' => 'general']);
        $user = User::factory()->create();
        $county = County::first();

        $groupA = Group::create([
            'name' => 'Group A',
            'slug' => 'group-a',
            'county_id' => $county->id,
            'is_active' => true,
        ]);

        $groupB = Group::create([
            'name' => 'Group B',
            'slug' => 'group-b',
            'county_id' => $county->id,
            'is_active' => true,
        ]);

        // Group B has 2 threads
        ForumThread::create(['user_id' => $user->id, 'category_id' => $category->id, 'group_id' => $groupB->id, 'title' => 'B1', 'slug' => 'b1']);
        ForumThread::create(['user_id' => $user->id, 'category_id' => $category->id, 'group_id' => $groupB->id, 'title' => 'B2', 'slug' => 'b2']);

        // Group A has 1 thread
        ForumThread::create(['user_id' => $user->id, 'category_id' => $category->id, 'group_id' => $groupA->id, 'title' => 'A1', 'slug' => 'a1']);

        // Sort by thread_count desc
        $response = $this->getJson('/api/groups?sort=thread_count&order=desc');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', 'Group B')
            ->assertJsonPath('data.1.name', 'Group A');
    }
}
