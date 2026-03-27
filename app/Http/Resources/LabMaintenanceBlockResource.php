<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * LabMaintenanceBlockResource
 *
 * Transforms LabMaintenanceBlock model to JSON response format.
 */
class LabMaintenanceBlockResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_space_id' => $this->lab_space_id,
            'block_type' => $this->block_type ?? 'maintenance',
            'title' => $this->title ?? null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'reason' => $this->reason ?? null,
            'completion_report' => $this->completion_report ?? null,
            'status' => $this->status ?? 'scheduled',
            'recurring' => (bool) ($this->recurring ?? false),
            'recurrence_rule' => $this->recurrence_rule ?? null,
            'recurrence_parent_id' => $this->recurrence_parent_id,
            'parent_recurrence_rule' => $this->whenLoaded('parent', fn() => $this->parent->recurrence_rule),
            'lab_space' => LabSpaceResource::make($this->whenLoaded('labSpace')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
