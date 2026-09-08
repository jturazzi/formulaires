<?php

namespace App\Models;

use Database\Factories\ResponseFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $form_id
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property Carbon $consented_at
 * @property Carbon $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 * @property-read EloquentCollection<int, Answer> $answers
 */
class Response extends Model
{
    /** @use HasFactory<ResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'email',
        'email_verified_at',
        'consented_at',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'consented_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
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

    /**
     * GDPR: delete the response along with every uploaded file.
     */
    public function purge(): void
    {
        foreach ($this->answers as $answer) {
            if ($answer->file_path) {
                Storage::disk('local')->delete($answer->file_path);
            }
        }

        $this->delete();
    }
}
