<?php

namespace App\Models;

use Database\Factories\AnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }
}
