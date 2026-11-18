<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Response;
use Illuminate\Support\Collection;

/**
 * Aggregated overview of every response to a form: global activity plus a
 * per-question breakdown (choice distribution, rating average, numeric
 * range, recent free-text answers…).
 */
class ResponseSummaryService
{
    private const SAMPLE_SIZE = 5;

    private const TIMELINE_DAYS = 30;

    private const RATING_SCALES = [
        'rating_star' => [1, 6],
        'rating_number' => [0, 10],
    ];

    /**
     * @param  Collection<int, FormField>  $fields  input fields, in display order
     */
    public function build(Form $form, Collection $fields): array
    {
        $total = $form->responses()->count();
        $fieldsById = $fields->keyBy('id');
        $accumulators = $fields->mapWithKeys(fn (FormField $field) => [
            $field->id => ['answered' => 0, 'counts' => [], 'other' => [], 'values' => [], 'samples' => []],
        ])->all();

        // Newest first, so free-text samples are the most recent answers.
        Answer::query()
            ->whereIn('form_field_id', array_keys($accumulators))
            ->select(['id', 'form_field_id', 'value', 'file_path'])
            ->lazyByIdDesc(1000)
            ->each(function (Answer $answer) use (&$accumulators, $fieldsById) {
                $this->accumulate($accumulators[$answer->form_field_id], $fieldsById[$answer->form_field_id], $answer);
            });

        return [
            'total' => $total,
            'last_7_days' => $form->responses()->where('submitted_at', '>=', now()->subDays(7))->count(),
            'last_submitted_at' => $form->responses()->max('submitted_at'),
            'timeline' => $total > 0 ? $this->timeline($form) : [],
            'fields' => $fields->map(fn (FormField $field) => $this->finalize($field, $accumulators[$field->id]))->values()->all(),
        ];
    }

    private function accumulate(array &$acc, FormField $field, Answer $answer): void
    {
        $value = $answer->value;

        if ($field->type === 'file') {
            if ($answer->file_path) {
                $acc['answered']++;
            }

            return;
        }

        if ($value === null || $value === '' || $value === []) {
            return;
        }

        $acc['answered']++;

        if (in_array($field->type, FormField::CHOICE_TYPES, true)) {
            $choices = $field->options['choices'] ?? [];

            foreach ((array) $value as $item) {
                $item = (string) $item;

                if (in_array($item, $choices, true)) {
                    $acc['counts'][$item] = ($acc['counts'][$item] ?? 0) + 1;
                } else {
                    $acc['other'][$item] = ($acc['other'][$item] ?? 0) + 1;
                }
            }

            return;
        }

        if (in_array($field->type, ['number', 'rating_star', 'rating_number'], true) && is_numeric($value)) {
            $acc['values'][] = (float) $value;

            return;
        }

        if (in_array($field->type, ['date', 'time'], true)) {
            $acc['values'][] = (string) $value;

            return;
        }

        if (count($acc['samples']) < self::SAMPLE_SIZE) {
            $acc['samples'][] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
    }

    private function finalize(FormField $field, array $acc): array
    {
        $base = ['field_id' => $field->id, 'answered' => $acc['answered']];

        if (in_array($field->type, FormField::CHOICE_TYPES, true)) {
            arsort($acc['other']);

            $options = array_map(
                fn (string $choice) => ['label' => $choice, 'count' => $acc['counts'][$choice] ?? 0, 'other' => false],
                $field->options['choices'] ?? [],
            );

            if ($acc['other']) {
                $options[] = ['label' => null, 'count' => array_sum($acc['other']), 'other' => true];
            }

            return [...$base, 'kind' => 'choices', 'options' => $options, 'samples' => array_slice(array_keys($acc['other']), 0, self::SAMPLE_SIZE)];
        }

        if (isset(self::RATING_SCALES[$field->type])) {
            [$min, $max] = self::RATING_SCALES[$field->type];
            $distribution = array_count_values(array_map('intval', $acc['values']));

            return [
                ...$base,
                'kind' => 'rating',
                'average' => $this->average($acc['values']),
                'max' => $max,
                'options' => array_map(
                    fn (int $score) => ['label' => (string) $score, 'count' => $distribution[$score] ?? 0, 'other' => false],
                    range($min, $max),
                ),
            ];
        }

        if ($field->type === 'number') {
            return [
                ...$base,
                'kind' => 'number',
                'average' => $this->average($acc['values']),
                'min' => $acc['values'] ? min($acc['values']) : null,
                'max' => $acc['values'] ? max($acc['values']) : null,
            ];
        }

        if (in_array($field->type, ['date', 'time'], true)) {
            // Y-m-d and H:i values sort chronologically as plain strings.
            return [
                ...$base,
                'kind' => 'range',
                'min' => $acc['values'] ? min($acc['values']) : null,
                'max' => $acc['values'] ? max($acc['values']) : null,
            ];
        }

        if ($field->type === 'file') {
            return [...$base, 'kind' => 'file'];
        }

        return [...$base, 'kind' => 'text', 'samples' => $acc['samples']];
    }

    /** @param  list<float>  $numbers */
    private function average(array $numbers): ?float
    {
        return $numbers ? round(array_sum($numbers) / count($numbers), 2) : null;
    }

    /**
     * Responses per day over the last TIMELINE_DAYS days, zero-filled.
     *
     * @return list<array{date: string, count: int}>
     */
    private function timeline(Form $form): array
    {
        $start = now()->subDays(self::TIMELINE_DAYS - 1)->startOfDay();

        $counts = $form->responses()
            ->where('submitted_at', '>=', $start)
            ->get(['submitted_at'])
            ->countBy(fn (Response $response) => $response->submitted_at->toDateString());

        return collect(range(0, self::TIMELINE_DAYS - 1))
            ->map(function (int $offset) use ($start, $counts) {
                $date = $start->copy()->addDays($offset)->toDateString();

                return ['date' => $date, 'count' => $counts->get($date, 0)];
            })
            ->all();
    }
}
