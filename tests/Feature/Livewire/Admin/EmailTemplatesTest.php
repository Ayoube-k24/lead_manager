<?php

use App\Models\EmailTemplate;
use App\Models\Role;
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

test('super admin can view email templates list', function () {
    EmailTemplate::factory()->count(3)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.email-templates'));

    $response->assertOk()
        ->assertSee('Templates d\'email')
        ->assertSee('Gérez vos templates d\'email de validation');
});

test('super admin can search email templates', function () {
    EmailTemplate::factory()->create(['name' => 'Welcome Email']);
    EmailTemplate::factory()->create(['name' => 'Confirmation Email']);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates')
        ->set('search', 'Welcome')
        ->assertSee('Welcome Email')
        ->assertDontSee('Confirmation Email');
});

test('super admin can delete email template', function () {
    $template = EmailTemplate::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates')
        ->call('delete', $template->id);

    expect(EmailTemplate::find($template->id))->toBeNull();
});
