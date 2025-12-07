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

        // Add CORS middleware for public form submissions (must be prepended)
        $middleware->web(prepend: [
            \App\Http\Middleware\HandleCors::class,
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

        // Import MailWizz leads - dynamic frequency from config
        $schedule->call(function () {
            $configs = \App\Models\MailWizzConfig::where('is_active', true)->get();

            foreach ($configs as $config) {
                // Vérifier si c'est le moment d'importer selon la fréquence
                $shouldImport = false;

                if (! $config->last_import_at) {
                    $shouldImport = true;
                } else {
                    $minutesSinceLastImport = now()->diffInMinutes($config->last_import_at);
                    if ($minutesSinceLastImport >= $config->import_frequency) {
                        $shouldImport = true;
                    }
                }

                if ($shouldImport) {
                    \Artisan::call('mailwizz:import-leads', [
                        '--config-id' => $config->id,
                    ]);
                }
            }
        })->name('mailwizz-import-check')->everyMinute()->withoutOverlapping();

        // Retry failed jobs (especially confirmation emails) every 30 minutes
        $schedule->command('queue:retry-failed --limit=50 --older-than=30')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
