<?php

namespace Database\Factories;

use App\Models\RenewalPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RenewalPreferenceFactory extends Factory
{
    protected $model = RenewalPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'renewal_type' => $this->faker->randomElement(['automatic', 'manual']),
            'send_renewal_reminders' => $this->faker->boolean(),
            'reminder_days_before' => $this->faker->numberBetween(1, 30),
            'preferred_payment_method' => $this->faker->randomElement(['mobile_money', 'card', 'bank_transfer']),
            'auto_switch_to_free_on_expiry' => $this->faker->boolean(),
        ];
    }
}
