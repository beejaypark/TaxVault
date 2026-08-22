<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'identity_provider' => 'keycloak',
            'provider_subject' => fake()->unique()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'status' => 'active',
        ];
    }
}
