<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Form extends Model
{
    /** @use HasFactory<FormFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'primary_color',
        'require_email_verification',
        'notify_on_response',
        'max_responses',
        'expires_at',
        'retention_days',
        'success_message',
    ];

    protected function casts(): array
    {
        return [
            'require_email_verification' => 'boolean',
            'notify_on_response' => 'boolean',
            'max_responses' => 'integer',
            'retention_days' => 'integer',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->slug ??= Str::lower(Str::random(16));
        });

        static::deleting(function (Form $form) {
            $form->responses()->with('answers')->get()->each->purge();

            if ($form->logo_path) {
                Storage::disk('public')->delete($form->logo_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('position');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function isOpen(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_responses !== null && $this->responses()->count() >= $this->max_responses) {
            return false;
        }

        return true;
    }

    /**
     * Effective retention in days: the form's own value or the global default.
     */
    public function effectiveRetentionDays(): int
    {
        return $this->retention_days ?? (int) Setting::get('default_retention_days', 365);
    }
}
