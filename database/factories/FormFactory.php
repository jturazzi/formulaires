<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => Form::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => Form::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }
}
