<?php

namespace App\Providers;

use App\Session\SessionManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Use a session manager that emits the legacy 64-char CSRF token.
        $this->app->singleton('session', fn ($app) => new SessionManager($app));
        $this->app->singleton('session.store', fn ($app) => $app->make('session')->driver());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
