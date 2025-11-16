<?php

use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    require_once __DIR__.'/../../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->role()->associate(Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super_admin',
    ]));
    $this->superAdmin->save();
});

test('super admin can view smtp profiles list', function () {
    SmtpProfile::factory()->count(3)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.smtp-profiles'));

    $response->assertOk()
        ->assertSee('Profils SMTP')
        ->assertSee('Gérez vos profils SMTP réutilisables');
});

test('super admin can search smtp profiles', function () {
    SmtpProfile::factory()->create(['name' => 'Gmail Profile']);
    SmtpProfile::factory()->create(['name' => 'Outlook Profile']);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles')
        ->set('search', 'Gmail')
        ->assertSee('Gmail Profile')
        ->assertDontSee('Outlook Profile');
});

test('super admin can toggle smtp profile active status', function () {
    $profile = SmtpProfile::factory()->create(['is_active' => true]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles')
        ->call('toggleActive', $profile->id);

    expect($profile->fresh()->is_active)->toBeFalse();
});

test('super admin can delete smtp profile', function () {
    $profile = SmtpProfile::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.smtp-profiles')
        ->call('delete', $profile->id);

    expect(SmtpProfile::find($profile->id))->toBeNull();
});
