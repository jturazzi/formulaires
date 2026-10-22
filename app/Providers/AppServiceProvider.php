<?php

namespace App\Providers;

use App\Support\SentryQueueReporting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        SentryQueueReporting::register();

        Event::listen(SocialiteWasCalled::class, MicrosoftExtendSocialite::class);
    }
}
