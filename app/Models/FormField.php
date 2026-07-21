<?php

namespace App\Models;

use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    public const TYPES = [
        'text',
        'textarea',
        'email',
        'number',
        'date',
        'choice',
        'checkboxes',
        'dropdown',
        'file',
        'info',
    ];

    /** Types that carry a list of choices in options.choices */
    public const CHOICE_TYPES = ['choice', 'checkboxes', 'dropdown'];

    protected $fillable = [
        'form_section_id',
        'type',
        'label',
        'description',
        'required',
        'options',
        'visibility',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
            'visibility' => 'array',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Deleting a field cascades its answers at the database level, so the
        // uploaded files must be removed from disk here.
        static::deleting(function (FormField $field) {
            $field->answers()
                ->whereNotNull('file_path')
                ->pluck('file_path')
                ->each(fn (string $path) => Storage::disk('local')->delete($path));
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    public function isInput(): bool
    {
        return $this->type !== 'info';
    }

    /**
     * Whether this field should be shown, given the raw submitted value of
     * every field on the form (keyed by field id). Fields this field depends
     * on are resolved recursively so a chain of conditions collapses
     * correctly when an intermediate field is itself hidden.
     *
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<int, mixed>  $rawValues
     * @param  array<int, bool>  $seen  cycle guard, keyed by field id already being resolved
     */
    public function isVisible(Collection $fieldsById, array $rawValues, array &$seen = []): bool
    {
        $visibility = $this->visibility;

        if (! $visibility || empty($visibility['mode']) || empty($visibility['conditions'])) {
            return true;
        }

        if (isset($seen[$this->id])) {
            return true;
        }

        $seen[$this->id] = true;

        $results = array_map(function (array $condition) use ($fieldsById, $rawValues, &$seen) {
            $target = $fieldsById->get($condition['field_id']);
            $targetVisible = ! $target || $target->isVisible($fieldsById, $rawValues, $seen);
            $value = $targetVisible ? ($rawValues[$condition['field_id']] ?? null) : null;

            return $this->evaluateCondition($condition, $value);
        }, $visibility['conditions']);

        $matches = ($visibility['logic'] ?? 'all') === 'any'
            ? in_array(true, $results, true)
            : ! in_array(false, $results, true);

        return $visibility['mode'] === 'visible_if' ? $matches : ! $matches;
    }

    private function evaluateCondition(array $condition, mixed $value): bool
    {
        $target = (string) ($condition['value'] ?? '');
        $empty = $value === null || $value === '' || $value === [];

        return match ($condition['operator'] ?? 'equals') {
            'empty' => $empty,
            'not_empty' => ! $empty,
            'equals' => ! $empty && $this->stringifyValue($value) === $target,
            'not_equals' => $empty || $this->stringifyValue($value) !== $target,
            'contains' => $this->valueContains($value, $target),
            'not_contains' => ! $this->valueContains($value, $target),
            'greater_than' => ! $empty && $this->compareOrdered($value, $target) > 0,
            'less_than' => ! $empty && $this->compareOrdered($value, $target) < 0,
            default => false,
        };
    }

    private function stringifyValue(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }

    private function valueContains(mixed $value, string $target): bool
    {
        if (is_array($value)) {
            return in_array($target, array_map('strval', $value), true);
        }

        return str_contains((string) $value, $target);
    }

    private function compareOrdered(mixed $value, string $target): int
    {
        if (is_numeric($value) && is_numeric($target)) {
            return (float) $value <=> (float) $target;
        }

        $a = strtotime((string) $value);
        $b = strtotime($target);

        return ($a === false || $b === false) ? 0 : $a <=> $b;
    }
}
