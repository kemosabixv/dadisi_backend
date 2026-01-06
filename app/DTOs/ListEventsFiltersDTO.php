<?php

namespace App\DTOs;

class ListEventsFiltersDTO
{
    public function __construct(
        public ?string $status = null,
        public ?string $event_type = null,
        public ?bool $featured = null,
        public ?int $organizer_id = null,
        public ?string $search = null,
        public ?int $category_id = null,
        public ?int $tag_id = null,
        public ?int $county_id = null,
        public ?string $category = null,
        public ?string $tag = null,
        public ?string $type = null,
        public ?string $timeframe = null,
        public ?string $start_date = null,
        public ?string $end_date = null,
        public string $sort_by = 'starts_at',
        public string $sort_dir = 'asc',
        public bool $upcoming = false,
        public int $per_page = 15,
    ) {}

    /**
     * Create from FormRequest validated data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            status: $data['status'] ?? null,
            event_type: $data['event_type'] ?? null,
            featured: isset($data['featured']) ? filter_var($data['featured'], FILTER_VALIDATE_BOOLEAN) : null,
            organizer_id: isset($data['organizer_id']) ? (int) $data['organizer_id'] : null,
            search: $data['search'] ?? null,
            category_id: isset($data['category_id']) ? (int) $data['category_id'] : null,
            tag_id: isset($data['tag_id']) ? (int) $data['tag_id'] : null,
            county_id: isset($data['county_id']) ? (int) $data['county_id'] : null,
            category: $data['category'] ?? null,
            tag: $data['tag'] ?? null,
            type: $data['type'] ?? null,
            timeframe: $data['timeframe'] ?? null,
            start_date: $data['start_date'] ?? null,
            end_date: $data['end_date'] ?? null,
            sort_by: $data['sort_by'] ?? 'starts_at',
            sort_dir: $data['sort_dir'] ?? 'asc',
            upcoming: isset($data['upcoming']) ? filter_var($data['upcoming'], FILTER_VALIDATE_BOOLEAN) : false,
            per_page: isset($data['per_page']) ? (int) $data['per_page'] : 15,
        );
    }

    /**
     * Convert to array for query filtering
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'event_type' => $this->event_type,
            'featured' => $this->featured,
            'organizer_id' => $this->organizer_id,
            'search' => $this->search,
            'category_id' => $this->category_id,
            'tag_id' => $this->tag_id,
            'county_id' => $this->county_id,
            'category' => $this->category,
            'tag' => $this->tag,
            'type' => $this->type,
            'timeframe' => $this->timeframe,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'sort_by' => $this->sort_by,
            'sort_dir' => $this->sort_dir,
            'upcoming' => $this->upcoming,
            'per_page' => $this->per_page,
        ];
    }
}
