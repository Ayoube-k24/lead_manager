<?php

use App\Models\EmailTemplate;
use App\Models\Form;
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

test('super admin can view edit form page', function () {
    $form = Form::factory()->create();

    Volt::test('admin.forms.edit', ['form' => $form])
        ->actingAs($this->superAdmin)
        ->assertSee('Modifier le formulaire')
        ->assertSee($form->name);
});

test('super admin can update form', function () {
    $form = Form::factory()->create([
        'name' => 'Old Form',
        'description' => 'Old Description',
        'fields' => [
            [
                'name' => 'old_field',
                'type' => 'text',
                'label' => 'Old Field',
                'required' => false,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();

    Volt::test('admin.forms.edit', ['form' => $form])
        ->actingAs($this->superAdmin)
        ->set('name', 'New Form')
        ->set('description', 'New Description')
        ->set('fields', [
            [
                'name' => 'new_field',
                'type' => 'email',
                'label' => 'New Field',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ])
        ->set('smtp_profile_id', $smtpProfile->id)
        ->set('email_template_id', $emailTemplate->id)
        ->set('is_active', false)
        ->call('update')
        ->assertRedirect(route('admin.forms'));

    $form->refresh();
    expect($form->name)->toBe('New Form')
        ->and($form->description)->toBe('New Description')
        ->and($form->smtp_profile_id)->toBe($smtpProfile->id)
        ->and($form->email_template_id)->toBe($emailTemplate->id)
        ->and($form->is_active)->toBeFalse();
});

test('super admin can add and remove fields when editing form', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'field1',
                'type' => 'text',
                'label' => 'Field 1',
                'required' => false,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    Volt::test('admin.forms.edit', ['form' => $form])
        ->actingAs($this->superAdmin)
        ->call('addField')
        ->assertSet('fields', function ($fields) {
            return count($fields) === 2;
        })
        ->call('removeField', 1)
        ->assertSet('fields', function ($fields) {
            return count($fields) === 1;
        });
});

test('super admin cannot update form with invalid data', function () {
    $form = Form::factory()->create();

    Volt::test('admin.forms.edit', ['form' => $form])
        ->actingAs($this->superAdmin)
        ->set('name', '')
        ->set('fields', [])
        ->call('update')
        ->assertHasErrors(['name', 'fields']);
});
