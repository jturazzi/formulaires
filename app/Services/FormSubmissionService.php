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
    public function rules(Form $form, Request $request): array
    {
        $rules = [
            'consent' => ['accepted'],
        ];

        if ($form->require_email_verification) {
            $rules['email'] = ['required', 'email:filter', 'max:255'];
            $rules['code'] = ['required', 'digits:6'];
        }

        $fieldsById = $form->fields->keyBy('id');
        $rawValues = $this->rawAnswerValues($form, $request);

        foreach ($form->fields as $field) {
            if (! $field->isInput()) {
                continue;
            }

            $key = "answers.{$field->id}";

            if (! $field->isVisible($fieldsById, $rawValues)) {
                $rules[$key] = ['nullable'];

                if ($field->type === 'checkboxes') {
                    $rules["{$key}.*"] = ['nullable'];
                }

                continue;
            }

            $required = $field->required ? 'required' : 'nullable';
            $options = $field->options ?? [];
            $choices = $options['choices'] ?? [];

            $allowOther = (bool) ($options['allow_other'] ?? false);

            $rules[$key] = match ($field->type) {
                'text' => [$required, 'string', 'max:'.($options['max_length'] ?? 255)],
                'textarea' => [$required, 'string', 'max:'.($options['max_length'] ?? 5000)],
                'email' => [$required, 'email:filter', 'max:255'],
                'phone' => [$required, 'string', 'max:30', 'regex:/^[0-9+\-\s().]+$/'],
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
                'time' => [$required, 'date_format:H:i'],
                'choice', 'dropdown' => $allowOther
                    ? [$required, 'string', 'max:500']
                    : [$required, 'string', Rule::in($choices)],
                'checkboxes' => [$required, 'array', ...($field->required ? ['min:1'] : [])],
                'rating_star' => [$required, 'integer', 'between:1,6'],
                'rating_number' => [$required, 'integer', 'between:0,10'],
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
     * Human-readable names for the "answers.{id}" validation keys, so error
     * messages reference the actual question instead of a raw field id.
     *
     * @return array<string, string>
     */
    public function attributes(Form $form): array
    {
        return $form->fields
            ->filter(fn ($field) => $field->isInput())
            ->mapWithKeys(fn ($field) => ["answers.{$field->id}" => $field->label])
            ->all();
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

            $fieldsById = $form->fields->keyBy('id');
            $rawValues = $this->rawAnswerValues($form, $request);

            foreach ($form->fields as $field) {
                if (! $field->isInput()) {
                    continue;
                }

                // A field hidden by its visibility conditions never gets a stored
                // answer, even if a value slipped through in the request payload.
                if (! $field->isVisible($fieldsById, $rawValues)) {
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

    /**
     * Raw (unvalidated) submitted value per field id, used to evaluate
     * visibility conditions before/independently of the validated payload.
     *
     * @return array<int, mixed>
     */
    private function rawAnswerValues(Form $form, Request $request): array
    {
        $values = [];

        foreach ($form->fields as $field) {
            $values[$field->id] = $field->type === 'file'
                ? ($request->hasFile("answers.{$field->id}") ? '1' : null)
                : $request->input("answers.{$field->id}");
        }

        return $values;
    }
}
