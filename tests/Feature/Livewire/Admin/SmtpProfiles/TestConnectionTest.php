<?php

use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    require_once __DIR__.'/../../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->superAdmin = User::factory()->withoutTwoFactor()->create();
    $this->superAdmin->role()->associate(Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super_admin',
    ]));
    $this->superAdmin->save();
});

test('super admin can test smtp connection in create page', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.create')
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        ->set('password', 'password123')
        ->call('testConnection')
        ->assertSet('isTesting', false)
        ->assertSet('testResult', fn ($value) => ! empty($value));
});

test('super admin cannot test smtp connection with missing fields', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.create')
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        // Missing username and password
        ->call('testConnection')
        ->assertHasErrors(['username', 'password']);
});

test('super admin can test smtp connection in edit page with existing password', function () {
    $profile = SmtpProfile::factory()->create([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'test@example.com',
        'password' => 'existing_password',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        // Password not set - should use existing password
        ->call('testConnection')
        ->assertSet('isTesting', false)
        ->assertSet('testResult', fn ($value) => ! empty($value));
});

test('super admin can test smtp connection in edit page with new password', function () {
    $profile = SmtpProfile::factory()->create([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'test@example.com',
        'password' => 'old_password',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        ->set('password', 'new_password')
        ->call('testConnection')
        ->assertSet('isTesting', false)
        ->assertSet('testResult', fn ($value) => ! empty($value));
});

test('super admin cannot test smtp connection in edit page without password when profile has no password', function () {
    $profile = SmtpProfile::factory()->create([
        'host' => 'smtp.example.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'test@example.com',
        'password' => 'existing_password',
    ]);

    // Simulate a profile without password by clearing it
    $profile->password = '';
    $profile->save();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        // No password set
        ->call('testConnection')
        ->assertSet('testSuccess', false)
        ->assertSet('testResult', fn ($value) => str_contains($value, 'mot de passe'));
});
