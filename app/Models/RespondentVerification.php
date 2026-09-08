<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $form_id
 * @property string $email
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 */
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

    /** @return BelongsTo<Form, $this> */
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
