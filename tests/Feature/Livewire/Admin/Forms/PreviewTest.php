<?php

use App\Models\Form;
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

test('super admin can preview form', function () {
    $form = Form::factory()->create([
        'name' => 'Test Form',
        'description' => 'Test Description',
        'fields' => [
            [
                'name' => 'email',
                'type' => 'email',
                'label' => 'Email Address',
                'placeholder' => 'Enter your email',
                'required' => true,
                'validation_rules' => [],
                'options' => [],
            ],
            [
                'name' => 'name',
                'type' => 'text',
                'label' => 'Full Name',
                'placeholder' => 'Enter your name',
                'required' => false,
                'validation_rules' => [],
                'options' => [],
            ],
        ],
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.preview', ['form' => $form])
        ->assertSee('Prévisualisation du formulaire')
        ->assertSee('Test Form')
        ->assertSee('Test Description')
        ->assertSee('Email Address')
        ->assertSee('Full Name');
});

test('super admin can preview form with select field', function () {
    $form = Form::factory()->create([
        'fields' => [
            [
                'name' => 'country',
                'type' => 'select',
                'label' => 'Country',
                'required' => true,
                'validation_rules' => [],
                'options' => ['FR', 'US', 'UK'],
            ],
        ],
    ]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms.preview', ['form' => $form])
        ->assertSee('Country')
        ->assertSee('FR')
        ->assertSee('US')
        ->assertSee('UK');
});
