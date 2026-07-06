<?php

use App\Models\RespondentVerification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// GDPR retention: purge responses past their form's retention period.
Schedule::command('responses:purge')->dailyAt('03:00');

// Clean up expired respondent email-verification codes.
Schedule::call(fn () => RespondentVerification::query()->where('expires_at', '<', now())->delete())
    ->hourly()
    ->name('purge-expired-respondent-verifications');
