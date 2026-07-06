<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSection>
 */
class FormSectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'title' => fake()->sentence(3),
            'position' => 0,
        ];
    }
}
