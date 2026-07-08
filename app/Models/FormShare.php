<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormShare extends Model
{
    protected $fillable = [
        'user_id',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
