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

    Volt::route('agent/dashboard', 'dashboard.agent')
        ->middleware('role:agent')
        ->name('dashboard.agent');

    // Sprint 3: Gestion des leads par les agents
    Route::middleware('role:agent')->group(function () {
        Volt::route('agent/leads', 'agent.leads')
            ->name('agent.leads');
        Volt::route('agent/leads/{lead}', 'agent.leads.show')
            ->name('agent.leads.show');
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
    });
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

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
