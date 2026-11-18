<?php

namespace App\Models\Concerns;

use App\Models\FormField;
use Illuminate\Support\Collection;

/**
 * Conditional display rules ("visible if" / "hidden if") stored in a
 * `visibility` JSON column, evaluated against the raw submitted values.
 *
 * @property array{mode?: string, logic?: string, conditions?: list<array{field_id: int, operator: string, value?: string|null}>}|null $visibility
 */
trait HasVisibilityConditions
{
    /**
     * Whether this element's own conditions are satisfied. Fields referenced
     * by a condition are resolved recursively (a hidden field counts as
     * unanswered) so chains of conditions collapse correctly.
     *
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<int, mixed>  $rawValues
     * @param  array<string, bool>  $resolving  cycle guard: elements on the current resolution path
     */
    protected function passesVisibilityConditions(Collection $fieldsById, array $rawValues, array &$resolving): bool
    {
        $visibility = $this->visibility;

        if (! $visibility || empty($visibility['mode']) || empty($visibility['conditions'])) {
            return true;
        }

        $key = static::class.':'.$this->id;

        if (isset($resolving[$key])) {
            return true;
        }

        $resolving[$key] = true;

        $results = array_map(function (array $condition) use ($fieldsById, $rawValues, &$resolving) {
            $target = $fieldsById->get($condition['field_id']);
            $targetVisible = ! $target || $target->isVisible($fieldsById, $rawValues, $resolving);
            $value = $targetVisible ? ($rawValues[$condition['field_id']] ?? null) : null;

            return $this->evaluateCondition($condition, $value);
        }, $visibility['conditions']);

        unset($resolving[$key]);

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
