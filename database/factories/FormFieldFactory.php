<?php

namespace Database\Factories;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'type' => 'text',
            'label' => fake()->sentence(3),
            'required' => false,
            'position' => 0,
        ];
    }

    public function type(string $type, array $options = []): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'options' => $options ?: match (true) {
                in_array($type, FormField::CHOICE_TYPES) => ['choices' => ['Option A', 'Option B', 'Option C']],
                default => null,
            },
        ]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['required' => true]);
    }
}
