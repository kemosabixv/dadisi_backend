<?php

namespace App\Services\Contracts;

use App\Models\Tag;
use Illuminate\Pagination\LengthAwarePaginator;

interface TagServiceContract
{
    /**
     * List all tags with optional filters.
     */
    public function listTags(array $filters = [], int $perPage = 50): LengthAwarePaginator;

    /**
     * Get a single tag by ID.
     */
    public function getTagById(int $id): Tag;

    /**
     * Create a new tag.
     */
    public function createTag(array $data): Tag;

    /**
     * Update an existing tag.
     */
    public function updateTag(Tag $tag, array $data): Tag;

    /**
     * Delete a tag.
     */
    public function deleteTag(Tag $tag): bool;

    /**
     * Get tag metadata for creation form.
     */
    public function getCreationMetadata(): array;

    /**
     * Get tag metadata for editing form.
     */
    public function getEditMetadata(Tag $tag): array;
}
