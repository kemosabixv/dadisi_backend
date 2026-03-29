<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'nullable|exists:forum_categories,id',
            'county_id' => 'nullable|exists:counties,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:forum_tags,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
            'image' => 'nullable|image|max:5120', // 5MB max
            'media_id' => 'nullable|integer|exists:media,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            if (!$user) return;

            $isStaff = $user->roles()->whereIn('name', ['admin', 'super_admin', 'moderator', 'staff', 'lab_supervisor'])->exists();

            // Registered users cannot set categories
            if (!$isStaff && $this->filled('category_id')) {
                $validator->errors()->add('category_id', 'Only staff can assign categories.');
            }

            // Mandatory tags for non-staff topics
            if (!$isStaff) {
                $hasCounty = $this->filled('county_id');
                $hasTags = $this->filled('tag_ids') && is_array($this->tag_ids) && count($this->tag_ids) > 0;

                if (!$hasCounty && !$hasTags) {
                    $validator->errors()->add('tags', 'Either a county tag or a custom tag is required to post.');
                }
            }
        });
    }

    public function bodyParameters(): array
    {
        return [
            'category_id' => ['description' => 'Forum category ID', 'example' => 1],
            'county_id' => ['description' => 'County ID (optional)', 'example' => 35],
            'title' => ['description' => 'Thread title', 'example' => 'How to get started with Dadisi Labs?'],
            'content' => ['description' => 'Thread content (min 10 chars)', 'example' => 'I would like to learn more about using the lab facilities.'],
        ];
    }
}
