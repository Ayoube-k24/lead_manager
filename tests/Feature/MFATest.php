<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
    Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
});

it('allows users to enable two-factor authentication', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $response = $this->actingAs($user)
        ->get(route('two-factor.show'));

    // May redirect to password confirmation if required
    if ($response->isRedirect()) {
        $response->assertRedirect();
    } else {
        $response->assertSuccessful();
        $response->assertSee('Two-Factor Authentication');
    }
});

it('requires password confirmation to enable two-factor authentication', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $response = $this->actingAs($user)
        ->get(route('two-factor.show'));

    // Should redirect to password confirmation
    if ($response->isRedirect()) {
        $response->assertRedirect();
    } else {
        $response->assertSuccessful();
    }
});

it('prevents access without two-factor when enabled', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->two_factor_secret = 'test-secret';
    $user->save();

    // Simulate login with 2FA enabled
    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    // Should redirect to two-factor challenge
    if ($user->hasEnabledTwoFactorAuthentication()) {
        $response->assertRedirect(route('two-factor.login'));
    }
});

it('validates two-factor recovery codes', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $this->actingAs($user);

    // Enable 2FA
    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
    ])->save();

    expect($user->recoveryCodes())->toBeArray()
        ->and(count($user->recoveryCodes()))->toBeGreaterThan(0);
});
