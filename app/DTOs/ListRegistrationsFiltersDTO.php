<?php

namespace App\DTOs;

class ListRegistrationsFiltersDTO
{
    public function __construct(
        public ?string $status = null,
        public ?bool $waitlist = null,
        public int $per_page = 50,
    ) {}

    /**
     * Create from FormRequest validated data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            status: $data['status'] ?? null,
            waitlist: $data['waitlist'] ?? null,
            per_page: $data['per_page'] ?? 50,
        );
    }

    /**
     * Convert to array for filtering
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'waitlist' => $this->waitlist,
            'per_page' => $this->per_page,
        ];
    }
}
