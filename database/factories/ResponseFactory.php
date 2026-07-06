<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Response>
 */
class ResponseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'consented_at' => now(),
            'submitted_at' => now(),
        ];
    }
}
