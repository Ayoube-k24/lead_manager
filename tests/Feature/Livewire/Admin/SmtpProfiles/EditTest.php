<?php

use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Livewire\Volt\Volt;

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

test('super admin can view edit smtp profile page', function () {
    $profile = SmtpProfile::factory()->create();

    Volt::test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->actingAs($this->superAdmin)
        ->assertSee('Modifier le profil SMTP')
        ->assertSee($profile->name);
});

test('super admin can update smtp profile', function () {
    $profile = SmtpProfile::factory()->create([
        'name' => 'Old Profile',
        'host' => 'old.smtp.com',
        'port' => 587,
    ]);

    Volt::test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->actingAs($this->superAdmin)
        ->set('name', 'New Profile')
        ->set('host', 'new.smtp.com')
        ->set('port', 465)
        ->set('encryption', 'ssl')
        ->set('username', 'newuser')
        ->set('from_address', 'new@example.com')
        ->set('from_name', 'New Name')
        ->set('is_active', false)
        ->call('update')
        ->assertRedirect(route('admin.smtp-profiles'));

    $profile->refresh();
    expect($profile->name)->toBe('New Profile')
        ->and($profile->host)->toBe('new.smtp.com')
        ->and($profile->port)->toBe(465)
        ->and($profile->encryption)->toBe('ssl')
        ->and($profile->is_active)->toBeFalse();
});

test('super admin can update smtp profile without changing password', function () {
    $profile = SmtpProfile::factory()->create([
        'password' => 'old_password',
    ]);

    Volt::test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->actingAs($this->superAdmin)
        ->set('name', 'Updated Profile')
        ->set('password', '') // Empty password should not update
        ->call('update')
        ->assertRedirect(route('admin.smtp-profiles'));

    $profile->refresh();
    expect($profile->name)->toBe('Updated Profile');
    // Password should remain unchanged
    expect($profile->password)->toBe('old_password');
});

test('super admin can update smtp profile with new password', function () {
    $profile = SmtpProfile::factory()->create();

    Volt::test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->actingAs($this->superAdmin)
        ->set('password', 'new_password')
        ->call('update')
        ->assertRedirect(route('admin.smtp-profiles'));

    $profile->refresh();
    expect($profile->password)->toBe('new_password');
});

test('super admin cannot update smtp profile with invalid data', function () {
    $profile = SmtpProfile::factory()->create();

    Volt::test('admin.smtp-profiles.edit', ['smtpProfile' => $profile])
        ->actingAs($this->superAdmin)
        ->set('name', '')
        ->set('host', '')
        ->set('port', 0)
        ->call('update')
        ->assertHasErrors(['name', 'host', 'port']);
});
