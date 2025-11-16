<?php

use App\Models\Form;
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

test('super admin can view forms list', function () {
    Form::factory()->count(3)->create();

    // Test using the route instead of Volt::test
    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.forms'));

    $response->assertOk()
        ->assertSee('Formulaires')
        ->assertSee('Gérez vos formulaires de capture de leads');
});

test('super admin can search forms', function () {
    Form::factory()->create(['name' => 'Contact Form']);
    Form::factory()->create(['name' => 'Newsletter Form']);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms')
        ->set('search', 'Contact')
        ->assertSee('Contact Form')
        ->assertDontSee('Newsletter Form');
});

test('super admin can toggle form active status', function () {
    $form = Form::factory()->create(['is_active' => true]);

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms')
        ->call('toggleActive', $form->id);

    expect($form->fresh()->is_active)->toBeFalse();
});

test('super admin can delete form', function () {
    $form = Form::factory()->create();

    Livewire::actingAs($this->superAdmin)
        ->test('admin.forms')
        ->call('delete', $form->id);

    expect(Form::find($form->id))->toBeNull();
});
