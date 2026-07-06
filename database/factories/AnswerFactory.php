<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\FormField;
use App\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'response_id' => Response::factory(),
            'form_field_id' => FormField::factory(),
            'value' => fake()->sentence(),
        ];
    }
}
