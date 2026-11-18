<?php

namespace App\Models;

use App\Models\Concerns\HasVisibilityConditions;
use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $form_id
 * @property int|null $form_section_id
 * @property string $type
 * @property string $label
 * @property string|null $description
 * @property bool $required
 * @property array<string, mixed>|null $options
 * @property array{mode?: string, logic?: string, conditions?: list<array{field_id: int, operator: string, value?: string|null}>}|null $visibility
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 * @property-read FormSection|null $section
 * @property-read EloquentCollection<int, Answer> $answers
 */
class FormField extends Model
{
    /** @use HasFactory<FormFieldFactory> */
    use HasFactory;

    use HasVisibilityConditions;

    public const TYPES = [
        'text',
        'textarea',
        'email',
        'phone',
        'number',
        'date',
        'time',
        'choice',
        'checkboxes',
        'dropdown',
        'file',
        'rating_star',
        'rating_number',
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

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<Answer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    /** @return BelongsTo<FormSection, $this> */
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
     * every field on the form (keyed by field id): its section must be shown
     * and its own conditions satisfied.
     *
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<int, mixed>  $rawValues
     * @param  array<string, bool>  $resolving  cycle guard, see HasVisibilityConditions
     */
    public function isVisible(Collection $fieldsById, array $rawValues, array &$resolving = []): bool
    {
        if ($this->section && ! $this->section->isVisible($fieldsById, $rawValues, $resolving)) {
            return false;
        }

        return $this->passesVisibilityConditions($fieldsById, $rawValues, $resolving);
    }
}
