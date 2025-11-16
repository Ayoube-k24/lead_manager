<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

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

    // Sprint 2: Gestion des formulaires et profils SMTP (Super Admin uniquement)
    Route::middleware('role:super_admin')->group(function () {
        // Profils SMTP
        Volt::route('admin/smtp-profiles', 'admin.smtp-profiles')
            ->name('admin.smtp-profiles');
        Volt::route('admin/smtp-profiles/create', 'admin.smtp-profiles.create')
            ->name('admin.smtp-profiles.create');
        Volt::route('admin/smtp-profiles/{smtpProfile}/edit', 'admin.smtp-profiles.edit')
            ->name('admin.smtp-profiles.edit');

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
