<?php

namespace App\Models;

use Database\Factories\FormFieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'position',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
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
}
