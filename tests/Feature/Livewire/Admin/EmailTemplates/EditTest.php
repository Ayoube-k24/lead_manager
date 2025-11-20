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

test('super admin can view edit email template page', function () {
    $template = EmailTemplate::factory()->create();
    
    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.edit', ['emailTemplate' => $template])
        ->assertSee('Modifier le template d\'email')
        ->assertSee($template->name);
});

test('super admin can update email template', function () {
    $template = EmailTemplate::factory()->create([
        'name' => 'Old Template',
        'subject' => 'Old Subject',
        'body_html' => '<p>Old HTML</p>',
        'body_text' => 'Old Text',
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.edit', ['emailTemplate' => $template])
        ->set('name', 'New Template')
        ->set('subject', 'New Subject')
        ->set('body_html', '<p>New HTML</p>')
        ->set('body_text', 'New Text')
        ->call('update')
        ->assertRedirect(route('admin.email-templates'));

    $template->refresh();
    expect($template->name)->toBe('New Template')
        ->and($template->subject)->toBe('New Subject')
        ->and($template->body_html)->toBe('<p>New HTML</p>')
        ->and($template->body_text)->toBe('New Text');
});

test('super admin can update email template without body_text', function () {
    $template = EmailTemplate::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.edit', ['emailTemplate' => $template])
        ->set('body_text', null)
        ->call('update')
        ->assertRedirect(route('admin.email-templates'));

    $template->refresh();
    expect($template->body_text)->toBeNull();
});

test('super admin cannot update email template with invalid data', function () {
    $template = EmailTemplate::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.email-templates.edit', ['emailTemplate' => $template])
        ->set('name', '')
        ->set('subject', '')
        ->set('body_html', '')
        ->call('update')
        ->assertHasErrors(['name', 'subject', 'body_html']);
});
