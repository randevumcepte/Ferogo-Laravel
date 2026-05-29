<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Prod'da tüm URL üretimini HTTPS'e zorla (WebRTC mikrofon izni için zorunlu).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
