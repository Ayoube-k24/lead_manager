<?php

namespace App\Providers;

use App\Models\Lead;
use App\Observers\LeadObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        $this->configureRateLimiting();
        $this->registerObservers();
    }

    /**
     * Register model observers.
     */
    private function registerObservers(): void
    {
        Lead::observe(LeadObserver::class);
    }

    /**
     * Configure rate limiting for public endpoints.
     */
    private function configureRateLimiting(): void
    {
        // Rate limiting pour les soumissions de formulaires publics
        // Limite: 10 soumissions par minute par IP
        RateLimiter::for('form-submission', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
