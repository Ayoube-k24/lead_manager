<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Vérifier que les utilisateurs connectés sont actifs
        $middleware->web(append: [
            \App\Http\Middleware\EnsureUserIsActive::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'forms/*/submit',
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Distribute unassigned leads every 5 minutes
        $schedule->command('leads:distribute-unassigned --limit=50')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Send reminder notifications every hour
        $schedule->command('reminders:notify')
            ->hourly()
            ->withoutOverlapping();

        // Check alerts every 15 minutes
        $schedule->command('alerts:check')
            ->everyFifteenMinutes()
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
