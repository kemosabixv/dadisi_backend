<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\UserPaymentMethod;
use App\Models\User;

class UserPaymentMethodFactory extends Factory
{
    protected $model = UserPaymentMethod::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'type' => 'phone',
            'identifier' => '254712345678',
            'data' => [],
            'is_primary' => true,
            'is_active' => true,
            'label' => 'Mobile Money',
        ];
    }
}
