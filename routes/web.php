<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
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
