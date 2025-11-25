<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    require_once __DIR__.'/Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->superAdminRole = Role::factory()->create([
        'name' => 'Super Admin',
        'slug' => 'super_admin',
    ]);

    $this->superAdmin = User::factory()->withoutTwoFactor()->create();
    $this->superAdmin->role()->associate($this->superAdminRole);
    $this->superAdmin->save();
});

test('super admin can view leads grouped by call center', function () {
    $ownerRole = Role::factory()->create([
        'name' => 'Owner',
        'slug' => 'call_center_owner',
    ]);

    $ownerA = User::factory()->withoutTwoFactor()->create();
    $ownerA->role()->associate($ownerRole)->save();

    $ownerB = User::factory()->withoutTwoFactor()->create();
    $ownerB->role()->associate($ownerRole)->save();

    $centerA = CallCenter::factory()->create([
        'name' => 'Centre Alpha',
        'owner_id' => $ownerA->id,
    ]);

    $centerB = CallCenter::factory()->create([
        'name' => 'Centre Beta',
        'owner_id' => $ownerB->id,
    ]);

    $formA = Form::factory()->create([
        'name' => 'Offre Gold',
        'call_center_id' => $centerA->id,
    ]);

    $formB = Form::factory()->create([
        'name' => 'Offre Silver',
        'call_center_id' => $centerB->id,
    ]);

    \App\Models\Lead::factory()->create([
        'form_id' => $formA->id,
        'call_center_id' => $centerA->id,
        'status' => 'pending_call',
        'email' => 'alpha@example.com',
    ]);

    \App\Models\Lead::factory()->create([
        'form_id' => $formB->id,
        'call_center_id' => $centerB->id,
        'status' => 'confirmed',
        'email' => 'beta@example.com',
    ]);

    $component = Livewire::actingAs($this->superAdmin)
        ->test('admin.call-centers.leads')
        ->assertStatus(200)
        ->assertSee('Centre Alpha')
        ->assertSee('Centre Beta')
        ->assertSee('Veuillez sélectionner un centre d\'appels');

    // Select first center and verify its leads
    $component->set('selectedCenterId', $centerA->id)
        ->assertSee('Centre Alpha')
        ->assertSee('alpha@example.com')
        ->assertDontSee('beta@example.com');

    // Select second center and verify its leads
    $component->set('selectedCenterId', $centerB->id)
        ->assertSee('Centre Beta')
        ->assertSee('beta@example.com')
        ->assertDontSee('alpha@example.com');
});

