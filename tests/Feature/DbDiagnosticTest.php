<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DbDiagnosticTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_media_persistence()
    {
        $user = User::factory()->create();
        $media = Media::factory()->for($user)->create([
            'file_name' => 'diagnostic.jpg'
        ]);

        $this->assertDatabaseHas('media', [
            'id' => $media->id,
            'file_name' => 'diagnostic.jpg'
        ]);

        // Try to fetch it again
        $found = Media::find($media->id);
        $this->assertNotNull($found);
        $this->assertEquals('diagnostic.jpg', $found->file_name);
    }

    #[Test]
    public function test_transaction_rollback_behavior()
    {
        $user = User::factory()->create();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        
        Log::info('TransactionTest: User created', ['user_count' => User::count()]);

        try {
            DB::beginTransaction();
            $media = Media::factory()->for($user)->create();
            Log::info('TransactionTest: Media created in transaction', ['media_count' => Media::count()]);
            
            throw new \Exception("Force rollback");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('TransactionTest: Rolled back');
        }

        Log::info('TransactionTest: After rollback', [
            'user_count' => User::count(),
            'media_count' => Media::count()
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('media', []);
    }

    #[Test]
    public function test_reproduce_empty_table_issue()
    {
        Log::info('Diagnostic: Starting test_reproduce_empty_table_issue');
        
        DB::listen(function($query) {
            if (str_contains(strtolower($query->sql), 'delete') || str_contains(strtolower($query->sql), 'drop') || str_contains(strtolower($query->sql), 'truncate') || str_contains(strtolower($query->sql), 'rollback')) {
                Log::info('Diagnostic: SQL Query', [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'level' => DB::transactionLevel()
                ]);
            }
        });

        Log::info('Diagnostic: Starting Level', ['level' => DB::transactionLevel()]);

        $user1 = User::factory()->create(['id' => 15]);
        $user2 = User::factory()->create(['id' => 14]);
        
        Log::info('Diagnostic: Users created', [
            'user1_id' => $user1->id, 
            'user2_id' => $user2->id,
            'user_count' => User::count()
        ]);

        $media = Media::factory()->create([
            'user_id' =>  $user1->id,
            'file_name' => 'test.jpg'
        ]);

        Log::info('Diagnostic: Media created', [
            'id' => $media->id, 
            'user_id' => $media->user_id,
            'media_count' => Media::count()
        ]);

        $this->assertDatabaseHas('media', ['id' => $media->id]);

        // Attempt to delete as other user
        Log::info('Diagnostic: Attempting unauthorized delete');
        $response = $this->actingAs($user2)->deleteJson("/api/media/{$media->id}");

        Log::info('Diagnostic: Response status', ['status' => $response->status()]);
        Log::info('Diagnostic: Response content', ['content' => $response->json()]);
        
        $response->assertStatus(403);

        Log::info('Diagnostic: Counts after delete attempt', [
            'user_count' => User::count(),
            'media_count' => Media::count()
        ]);

        $this->assertDatabaseHas('users', ['id' => $user1->id]);
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }
}
