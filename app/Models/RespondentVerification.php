<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespondentVerification extends Model
{
    protected $fillable = [
        'form_id',
        'email',
        'code_hash',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function matches(string $code): bool
    {
        return ! $this->expires_at->isPast()
            && hash_equals($this->code_hash, hash('sha256', $code));
    }
}
