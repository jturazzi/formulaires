<?php

namespace App\Models;

use App\Models\Concerns\HasVisibilityConditions;
use Database\Factories\FormSectionFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $form_id
 * @property string|null $title
 * @property string|null $description
 * @property array{mode?: string, logic?: string, conditions?: list<array{field_id: int, operator: string, value?: string|null}>}|null $visibility
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 * @property-read EloquentCollection<int, FormField> $fields
 */
class FormSection extends Model
{
    /** @use HasFactory<FormSectionFactory> */
    use HasFactory;

    use HasVisibilityConditions;

    protected $fillable = [
        'title',
        'description',
        'visibility',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Form, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    /**
     * Whether this section (and therefore every field in it) is shown.
     *
     * @param  Collection<int, FormField>  $fieldsById
     * @param  array<int, mixed>  $rawValues
     * @param  array<string, bool>  $resolving  cycle guard, see HasVisibilityConditions
     */
    public function isVisible(Collection $fieldsById, array $rawValues, array &$resolving = []): bool
    {
        return $this->passesVisibilityConditions($fieldsById, $rawValues, $resolving);
    }
}
