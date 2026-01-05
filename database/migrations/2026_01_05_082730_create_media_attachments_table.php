<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration creates a polymorphic many-to-many pivot table for media attachments.
     * Media can be attached to multiple entities (posts, events, campaigns, speakers)
     * and entities can have multiple media items.
     */
    public function up(): void
    {
        // Create the pivot table
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->onDelete('cascade');
            $table->string('attachable_type'); // Polymorphic type (App\Models\Post, etc.)
            $table->unsignedBigInteger('attachable_id'); // Polymorphic ID
            $table->string('role', 50)->default('gallery'); // 'featured', 'gallery', 'speaker_photo', 'logo'
            $table->timestamps();

            // Indexes for performance
            $table->index(['attachable_type', 'attachable_id'], 'attachable_index');
            $table->index('media_id');
            $table->index('role');

            // Prevent duplicate attachments (same media, same entity, same role)
            $table->unique(['media_id', 'attachable_type', 'attachable_id', 'role'], 'unique_media_attachment');
        });

        // Migrate existing data from media.attached_to to pivot table
        $this->migrateExistingAttachments();
    }

    /**
     * Migrate existing media attachments from the old column-based system
     * to the new pivot table system.
     */
    private function migrateExistingAttachments(): void
    {
        // Get all media with attached_to set
        $attachedMedia = DB::table('media')
            ->whereNotNull('attached_to')
            ->whereNotNull('attached_to_id')
            ->get();

        foreach ($attachedMedia as $media) {
            // Convert old 'attached_to' string to full model class name
            $attachableType = $this->convertToModelClass($media->attached_to);

            // Insert into pivot table
            DB::table('media_attachments')->insert([
                'media_id' => $media->id,
                'attachable_type' => $attachableType,
                'attachable_id' => $media->attached_to_id,
                'role' => 'featured', // Assume old attachments were featured images
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Convert old attached_to string to full model class name
     */
    private function convertToModelClass(string $type): string
    {
        $mapping = [
            'post' => 'App\\Models\\Post',
            'event' => 'App\\Models\\Event',
            'campaign' => 'App\\Models\\DonationCampaign',
            'speaker' => 'App\\Models\\Speaker',
        ];

        return $mapping[$type] ?? 'App\\Models\\' . ucfirst($type);
    }

    /**
     * Reverse the migrations.
     * 
     * NOTE: We keep the attached_to columns in the media table for now
     * to allow for safe rollback. They will be removed in a future migration
     * after we verify the pivot table is working correctly.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
