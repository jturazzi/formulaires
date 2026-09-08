<?php

namespace App\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $response_id
 * @property int $form_field_id
 * @property mixed $value string or array depending on the field type
 * @property string|null $file_path
 * @property string|null $file_name
 * @property int|null $file_size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Response $response
 * @property-read FormField $field
 */
class Answer extends Model
{
    /** @use HasFactory<AnswerFactory> */
    use HasFactory;

    protected $fillable = [
        'form_field_id',
        'value',
        'file_path',
        'file_name',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
            'file_size' => 'integer',
        ];
    }

    /** @return BelongsTo<Response, $this> */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    /** @return BelongsTo<FormField, $this> */
    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
