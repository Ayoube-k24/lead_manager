<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

// Route d'accueil - affiche la page de login
Route::get('/', function () {
    /** @var \Illuminate\Contracts\Auth\Guard $guard */
    $guard = auth();
    if ($guard->check()) {
        return redirect()->route('dashboard');
    }

    return view('livewire.auth.login');
})->name('home');

// Redirection de /login vers / pour éviter la confusion
Route::get('/login', function () {
    return redirect()->route('home');
})->name('login');

// Sprint 3: Routes publiques pour les formulaires et confirmation email
Route::post('forms/{form:uid}/submit', [\App\Http\Controllers\PublicFormController::class, 'submit'])
    ->middleware('throttle:form-submission')
    ->name('forms.submit');

Route::get('leads/confirm-email/{token}', [\App\Http\Controllers\LeadConfirmationController::class, 'confirm'])
    ->name('leads.confirm-email');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        /** @var \Illuminate\Contracts\Auth\Guard $guard */
        $guard = auth();
        /** @var \App\Models\User|null $user */
        $user = $guard->user();

        // Load role with eager loading to avoid N+1 queries
        $user->loadMissing('role');

        if (! $user->role) {
            return redirect()->route('profile.edit');
        }

        return match ($user->role->slug) {
            'super_admin' => redirect()->route('dashboard.admin'),
            'call_center_owner' => redirect()->route('dashboard.owner'),
            'supervisor' => redirect()->route('dashboard.supervisor'),
            'agent' => redirect()->route('dashboard.agent'),
            default => redirect()->route('profile.edit'),
        };
    })->name('dashboard');

    Volt::route('admin/dashboard', 'dashboard.super-admin')
        ->middleware('role:super_admin')
        ->name('dashboard.admin');

    Volt::route('owner/dashboard', 'dashboard.call-center-owner')
        ->middleware('role:call_center_owner')
        ->name('dashboard.owner');

    Volt::route('supervisor/dashboard', 'dashboard.supervisor')
        ->middleware('role:supervisor')
        ->name('dashboard.supervisor');

    Volt::route('agent/dashboard', 'dashboard.agent')
        ->middleware('role:agent')
        ->name('dashboard.agent');

    // Sprint 3: Gestion des leads par les agents
    Route::middleware('role:agent')->group(function () {
        Volt::route('agent/leads', 'agent.leads')
            ->name('agent.leads');
        Volt::route('agent/leads/{lead}', 'agent.leads.show')
            ->name('agent.leads.show');

        // Sprint 8: Calendrier des rappels
        Volt::route('agent/reminders/calendar', 'reminders.calendar')
            ->name('agent.reminders.calendar');
    });

    // Gestion des agents par les superviseurs
    Route::middleware('role:supervisor')->group(function () {
        Volt::route('supervisor/agents', 'supervisor.agents')
            ->name('supervisor.agents');
        Volt::route('supervisor/agents/{user}/stats', 'supervisor.agents.stats')
            ->name('supervisor.agents.stats');
        Volt::route('supervisor/leads', 'supervisor.leads')
            ->name('supervisor.leads');
        Volt::route('supervisor/statistics', 'supervisor.statistics')
            ->name('supervisor.statistics');
    });

    // Sprint 4: Gestion des agents par les propriétaires de centres d'appels
    Route::middleware('role:call_center_owner')->group(function () {
        Volt::route('owner/agents', 'owner.agents')
            ->name('owner.agents');
        Volt::route('owner/agents/create', 'owner.agents.create')
            ->name('owner.agents.create');
        Volt::route('owner/agents/{user}/edit', 'owner.agents.edit')
            ->name('owner.agents.edit');
        Volt::route('owner/agents/{user}/stats', 'owner.agents.stats')
            ->name('owner.agents.stats');
        Volt::route('owner/leads', 'owner.leads')
            ->name('owner.leads');
        Volt::route('owner/leads/{lead}/assign', 'owner.leads.assign')
            ->name('owner.leads.assign');
        Volt::route('owner/distribution', 'owner.distribution')
            ->name('owner.distribution');

        // Sprint 5: Statistiques avancées (Propriétaire)
        Volt::route('owner/statistics', 'owner.statistics')
            ->name('owner.statistics');
        Route::get('owner/statistics/export/csv', [\App\Http\Controllers\ExportController::class, 'exportStatisticsCsv'])
            ->name('owner.statistics.export.csv');
        Route::get('owner/statistics/export/pdf', [\App\Http\Controllers\ExportController::class, 'exportStatisticsPdf'])
            ->name('owner.statistics.export.pdf');
        Route::get('owner/leads/export/csv', [\App\Http\Controllers\ExportController::class, 'exportLeadsCsv'])
            ->name('owner.leads.export.csv');

        // Sprint 8: Gestion des Tags (Call Center Owners)
        Volt::route('owner/tags', 'owner.tags')
            ->name('owner.tags');
        Volt::route('owner/tags/create', 'owner.tags.create')
            ->name('owner.tags.create');
        Volt::route('owner/tags/{tag}/edit', 'owner.tags.edit')
            ->name('owner.tags.edit');

        // Gestion des Statuts (Call Center Owners)
        Volt::route('owner/statuses', 'owner.statuses')
            ->name('owner.statuses');
        Volt::route('owner/statuses/create', 'owner.statuses.create')
            ->name('owner.statuses.create');
        Volt::route('owner/statuses/{status}/edit', 'owner.statuses.edit')
            ->name('owner.statuses.edit');
    });

    // Sprint 2: Gestion des formulaires et profils SMTP (Super Admin uniquement)
    Route::middleware('role:super_admin')->group(function () {
        // Profils SMTP
        Volt::route('admin/smtp-profiles', 'admin.smtp-profiles')
            ->name('admin.smtp-profiles');
        Volt::route('admin/smtp-profiles/create', 'admin.smtp-profiles.create')
            ->name('admin.smtp-profiles.create');
        Volt::route('admin/smtp-profiles/{smtpProfile}/edit', 'admin.smtp-profiles.edit')
            ->name('admin.smtp-profiles.edit');
        Volt::route('admin/call-centers', 'admin.call-centers')
            ->name('admin.call-centers');
        Volt::route('admin/call-centers/leads', 'admin.call-centers.leads')
            ->name('admin.call-centers.leads');

        // Templates d'email
        Volt::route('admin/email-templates', 'admin.email-templates')
            ->name('admin.email-templates');
        Volt::route('admin/email-templates/create', 'admin.email-templates.create')
            ->name('admin.email-templates.create');
        Volt::route('admin/email-templates/{emailTemplate}/edit', 'admin.email-templates.edit')
            ->name('admin.email-templates.edit');

        // Formulaires
        Volt::route('admin/forms', 'admin.forms')
            ->name('admin.forms');
        Volt::route('admin/forms/create', 'admin.forms.create')
            ->name('admin.forms.create');
        Volt::route('admin/forms/{form}/edit', 'admin.forms.edit')
            ->name('admin.forms.edit');
        Volt::route('admin/forms/{form}/preview', 'admin.forms.preview')
            ->name('admin.forms.preview');
        Volt::route('admin/forms/{form}/info', 'admin.forms.info')
            ->name('admin.forms.info');

        // Sprint 7: Webhooks
        Volt::route('admin/webhooks', 'admin.webhooks')
            ->name('admin.webhooks');
        Volt::route('admin/webhooks/create', 'admin.webhooks.create')
            ->name('admin.webhooks.create');
        Volt::route('admin/webhooks/{webhook}/edit', 'admin.webhooks.edit')
            ->name('admin.webhooks.edit');

        // Sprint 4: Gestion des leads (Super Admin)
        Volt::route('admin/leads', 'admin.leads')
            ->name('admin.leads');
        Volt::route('admin/leads/{lead}', 'admin.leads.show')
            ->name('admin.leads.show');

        // Sprint 5: Statistiques avancées (Super Admin)
        Volt::route('admin/statistics', 'admin.statistics')
            ->name('admin.statistics');
        Route::get('admin/statistics/export/csv', [\App\Http\Controllers\ExportController::class, 'exportStatisticsCsv'])
            ->name('admin.statistics.export.csv');
        Route::get('admin/statistics/export/pdf', [\App\Http\Controllers\ExportController::class, 'exportStatisticsPdf'])
            ->name('admin.statistics.export.pdf');
        Route::get('admin/leads/export/csv', [\App\Http\Controllers\ExportController::class, 'exportLeadsCsv'])
            ->name('admin.leads.export.csv');

        // Sprint 6: Audit des actions (Super Admin)
        Volt::route('admin/audit-logs', 'admin.audit-logs')
            ->name('admin.audit-logs');

        // Sprint 8: Gestion des Tags (Super Admin)
        Volt::route('admin/tags', 'admin.tags')
            ->name('admin.tags');
        Volt::route('admin/tags/create', 'admin.tags.create')
            ->name('admin.tags.create');
        Volt::route('admin/tags/{tag}/edit', 'admin.tags.edit')
            ->name('admin.tags.edit');

        // Gestion des Statuts (Super Admin)
        Volt::route('admin/statuses', 'admin.statuses')
            ->name('admin.statuses');
        Volt::route('admin/statuses/create', 'admin.statuses.create')
            ->name('admin.statuses.create');
        Volt::route('admin/statuses/{status}/edit', 'admin.statuses.edit')
            ->name('admin.statuses.edit');

        // Sprint 8: Configuration du Scoring (Super Admin)
        Volt::route('admin/scoring', 'admin.scoring')
            ->name('admin.scoring');

        // MailWizz Integration
        Volt::route('admin/mailwizz', 'admin.mailwizz.index')
            ->name('admin.mailwizz.index');
        Volt::route('admin/mailwizz/create', 'admin.mailwizz.create')
            ->name('admin.mailwizz.create');
        Volt::route('admin/mailwizz/{config}/edit', 'admin.mailwizz.edit')
            ->name('admin.mailwizz.edit');

        // API Tokens and Documentation
        Volt::route('admin/api-tokens', 'admin.api-tokens')
            ->name('admin.api-tokens');
        Volt::route('admin/api/documentation', 'admin.api-documentation')
            ->name('admin.api.documentation');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');
    Volt::route('settings/alerts', 'settings.alerts')->name('settings.alerts');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});
