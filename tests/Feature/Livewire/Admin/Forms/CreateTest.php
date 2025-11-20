<?php

use App\Models\EmailTemplate;
use App\Models\Form;
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

test('super admin can create form', function () {
    $smtpProfile = SmtpProfile::factory()->create();
    $emailTemplate = EmailTemplate::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.create')
        ->set('name', 'Test Form')
        ->set('description', 'Test Description')
        ->set('fields', [
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email',
                'placeholder' => 'Enter your email',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
        ])
        ->set('smtp_profile_id', $smtpProfile->id)
        ->set('email_template_id', $emailTemplate->id)
        ->set('is_active', true)
        ->call('store')
        ->assertRedirect(route('admin.forms'));

    expect(Form::where('name', 'Test Form')->exists())->toBeTrue();
});

test('super admin can add and remove form fields', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.create')
        ->call('addField')
        ->assertSet('fields', function ($fields) {
            return count($fields) === 2;
        })
        ->call('removeField', 1)
        ->assertSet('fields', function ($fields) {
            return count($fields) === 1;
        });
});

test('super admin can add options to select field', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.create')
        ->set('fields.0.type', 'select')
        ->call('addOption', 0)
        ->assertSet('fields.0.options', function ($options) {
            return count($options) === 1;
        })
        ->call('removeOption', 0, 0)
        ->assertSet('fields.0.options', function ($options) {
            return count($options) === 0;
        });
});

test('super admin cannot create form with invalid data', function () {
    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.create')
        ->set('name', '')
        ->set('fields', [])
        ->call('store')
        ->assertHasErrors(['name', 'fields']);
});
