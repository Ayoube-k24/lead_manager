<?php

use App\Models\EmailTemplate;
use App\Models\Role;
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

test('super admin can create email template', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.create')
        ->set('name', 'Test Template')
        ->set('subject', 'Test Subject')
        ->set('body_html', '<p>Test HTML</p>')
        ->set('body_text', 'Test Text')
        ->call('store')
        ->assertRedirect(route('admin.email-templates'));

    expect(EmailTemplate::where('name', 'Test Template')->exists())->toBeTrue();
});

test('super admin cannot create email template with invalid data', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.create')
        ->set('name', '')
        ->set('subject', 'Test Subject')
        ->call('store')
        ->assertHasErrors(['name']);
});
