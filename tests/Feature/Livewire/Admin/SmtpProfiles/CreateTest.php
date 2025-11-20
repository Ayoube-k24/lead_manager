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

test('super admin can create smtp profile', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.create')
        ->set('name', 'Test SMTP')
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        ->set('password', 'password123')
        ->set('from_address', 'noreply@example.com')
        ->set('from_name', 'Test Sender')
        ->set('is_active', true)
        ->call('store')
        ->assertRedirect(route('admin.smtp-profiles'));

    expect(SmtpProfile::where('name', 'Test SMTP')->exists())->toBeTrue();
});

test('super admin cannot create smtp profile with invalid data', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.create')
        ->set('name', '')
        ->set('host', 'smtp.example.com')
        ->call('store')
        ->assertHasErrors(['name']);
});

test('super admin cannot create smtp profile with invalid email', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles.create')
        ->set('name', 'Test SMTP')
        ->set('host', 'smtp.example.com')
        ->set('port', 587)
        ->set('encryption', 'tls')
        ->set('username', 'test@example.com')
        ->set('password', 'password123')
        ->set('from_address', 'invalid-email')
        ->call('store')
        ->assertHasErrors(['from_address']);
});
