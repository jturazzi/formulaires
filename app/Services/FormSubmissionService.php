<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FormSubmissionService
{
    /**
     * Build the Laravel validation rules for a public submission.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(Form $form): array
    {
        $rules = [
            'consent' => ['accepted'],
        ];

        if ($form->require_email_verification) {
            $rules['email'] = ['required', 'email:filter', 'max:255'];
            $rules['code'] = ['required', 'digits:6'];
        }

        foreach ($form->fields as $field) {
            if (! $field->isInput()) {
                continue;
            }

            $key = "answers.{$field->id}";
            $required = $field->required ? 'required' : 'nullable';
            $options = $field->options ?? [];
            $choices = $options['choices'] ?? [];

            $allowOther = (bool) ($options['allow_other'] ?? false);

            $rules[$key] = match ($field->type) {
                'text' => [$required, 'string', 'max:'.($options['max_length'] ?? 255)],
                'textarea' => [$required, 'string', 'max:'.($options['max_length'] ?? 5000)],
                'email' => [$required, 'email:filter', 'max:255'],
                'number' => [
                    $required,
                    'numeric',
                    ...(isset($options['min']) ? ['min:'.$options['min']] : []),
                    ...(isset($options['max']) ? ['max:'.$options['max']] : []),
                ],
                'date' => [
                    $required,
                    'date',
                    ...(isset($options['min_date']) ? ['after_or_equal:'.$options['min_date']] : []),
                    ...(isset($options['max_date']) ? ['before_or_equal:'.$options['max_date']] : []),
                ],
                'choice', 'dropdown' => $allowOther
                    ? [$required, 'string', 'max:500']
                    : [$required, 'string', Rule::in($choices)],
                'checkboxes' => [$required, 'array', ...($field->required ? ['min:1'] : [])],
                'file' => [
                    $required,
                    'file',
                    'extensions:'.implode(',', config('formulaires.allowed_extensions')),
                    'max:'.config('formulaires.max_upload_kb'),
                ],
                default => [$required],
            };

            if ($field->type === 'checkboxes') {
                $rules["{$key}.*"] = $allowOther ? ['string', 'max:500'] : ['string', Rule::in($choices)];
            }
        }

        return $rules;
    }

    /**
     * Persist a validated submission: response, answers and uploaded files.
     */
    public function store(Form $form, Request $request, array $validated): Response
    {
        return DB::transaction(function () use ($form, $request, $validated) {
            $response = $form->responses()->create([
                'email' => $validated['email'] ?? null,
                'email_verified_at' => $form->require_email_verification ? now() : null,
                'consented_at' => now(),
                'submitted_at' => now(),
            ]);

            foreach ($form->fields as $field) {
                if (! $field->isInput()) {
                    continue;
                }

                $value = $validated['answers'][$field->id] ?? null;

                if ($field->type === 'file') {
                    $file = $request->file("answers.{$field->id}");

                    if ($file instanceof UploadedFile) {
                        $response->answers()->create([
                            'form_field_id' => $field->id,
                            'file_path' => $file->store("form-uploads/{$form->id}", 'local'),
                            'file_name' => $file->getClientOriginalName(),
                            'file_size' => $file->getSize(),
                        ]);
                    }

                    continue;
                }

                if ($value === null || $value === '' || $value === []) {
                    continue;
                }

                $response->answers()->create([
                    'form_field_id' => $field->id,
                    'value' => $value,
                ]);
            }

            return $response;
        });
    }
}
