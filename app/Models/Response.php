<?php

namespace App\Models;

use Database\Factories\ResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

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
