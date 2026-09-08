<?php

namespace App\Models;

use Database\Factories\FormFactory;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string|null $logo_path
 * @property string|null $primary_color
 * @property string $status
 * @property bool $require_email_verification
 * @property bool $notify_on_response
 * @property list<string>|null $notification_emails
 * @property int|null $max_responses
 * @property Carbon|null $expires_at
 * @property int|null $retention_days
 * @property string|null $success_message
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read EloquentCollection<int, FormSection> $sections
 * @property-read EloquentCollection<int, FormField> $fields
 * @property-read EloquentCollection<int, Response> $responses
 * @property-read EloquentCollection<int, FormShare> $shares
 * @property-read EloquentCollection<int, User> $collaborators
 */
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
        'logo_path',
        'description',
        'primary_color',
        'require_email_verification',
        'notify_on_response',
        'notification_emails',
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
            'notification_emails' => 'array',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<FormSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(FormSection::class)->orderBy('position');
    }

    /** @return HasMany<FormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    /** @return HasMany<Response, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /** @return HasMany<FormShare, $this> */
    public function shares(): HasMany
    {
        return $this->hasMany(FormShare::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'form_shares')->withTimestamps();
    }

    /**
     * Who gets the "new response" email. The owner is never included automatically —
     * they can add their own address to the list if they want it.
     *
     * @return list<string>
     */
    public function notificationRecipients(): array
    {
        return $this->notification_emails ?? [];
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
