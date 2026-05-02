<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // ── Modo estricto en desarrollo (previene N+1, mass assignment silencioso) ──
        if ($this->app->environment('local')) {
            Model::shouldBeStrict();
        }
    }
}
