<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'status' => 'open',
            'priority' => 'medium',
            'category' => 'general',
            'assigned_to' => null,
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function open(): self
    {
        return $this->state(['status' => 'open']);
    }

    public function resolved(): self
    {
        return $this->state([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by' => User::factory(),
        ]);
    }

    public function closed(): self
    {
        return $this->state([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    public function highPriority(): self
    {
        return $this->state(['priority' => 'high']);
    }
}
