<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->ownerRole = Role::firstOrCreate(
        ['slug' => 'call_center_owner'],
        ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
    );

    $this->callCenter = CallCenter::factory()->create();
    $this->owner = User::factory()->create([
        'role_id' => $this->ownerRole->id,
        'call_center_id' => $this->callCenter->id,
    ]);
});

test('owner can view statuses list', function () {
    $status = LeadStatus::create(['slug' => 'test1', 'name' => 'Test 1', 'color' => '#FF0000']);
    Lead::factory()->create(['status_id' => $status->id, 'call_center_id' => $this->callCenter->id]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statuses'));

    $response->assertSuccessful()
        ->assertSee('Test 1');
});

test('owner can create a status', function () {
    $response = $this->actingAs($this->owner)
        ->get(route('owner.statuses.create'));

    $response->assertSuccessful()
        ->assertSee('Créer un statut');
});

test('owner can store a new status', function () {
    Volt::test('owner.statuses.create')
        ->actingAs($this->owner)
        ->set('slug', 'new_status')
        ->set('name', 'New Status')
        ->set('color', '#FF0000')
        ->set('description', 'Test description')
        ->set('is_active', true)
        ->call('store')
        ->assertRedirect(route('owner.statuses'));

    $this->assertDatabaseHas('lead_statuses', [
        'slug' => 'new_status',
        'name' => 'New Status',
    ]);
});

test('owner can edit a status', function () {
    $status = LeadStatus::create([
        'slug' => 'edit_me',
        'name' => 'Edit Me',
        'color' => '#FF0000',
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statuses.edit', $status));

    $response->assertSuccessful()
        ->assertSee('Modifier le statut');
});

test('owner can update a status', function () {
    $status = LeadStatus::create([
        'slug' => 'update_me',
        'name' => 'Update Me',
        'color' => '#FF0000',
    ]);

    Volt::test('owner.statuses.edit', ['status' => $status])
        ->actingAs($this->owner)
        ->set('name', 'Updated Name')
        ->set('color', '#00FF00')
        ->call('update')
        ->assertRedirect(route('owner.statuses'));

    $this->assertDatabaseHas('lead_statuses', [
        'id' => $status->id,
        'name' => 'Updated Name',
        'color' => '#00FF00',
    ]);
});

test('owner can delete a non-system status', function () {
    $status = LeadStatus::create([
        'slug' => 'delete_me',
        'name' => 'Delete Me',
        'color' => '#FF0000',
        'is_system' => false,
    ]);

    Volt::test('owner.statuses')
        ->actingAs($this->owner)
        ->call('delete', $status)
        ->assertSuccessful();

    $this->assertDatabaseMissing('lead_statuses', ['id' => $status->id]);
});

test('owner sees only statuses used in their call center', function () {
    $status1 = LeadStatus::create(['slug' => 'used1', 'name' => 'Used 1', 'color' => '#FF0000']);
    $status2 = LeadStatus::create(['slug' => 'used2', 'name' => 'Used 2', 'color' => '#00FF00']);
    $status3 = LeadStatus::create(['slug' => 'unused', 'name' => 'Unused', 'color' => '#0000FF']);

    Lead::factory()->create(['status_id' => $status1->id, 'call_center_id' => $this->callCenter->id]);
    Lead::factory()->create(['status_id' => $status2->id, 'call_center_id' => $this->callCenter->id]);
    // status3 is not used in this call center

    Volt::test('owner.statuses')
        ->actingAs($this->owner)
        ->assertSee('Used 1')
        ->assertSee('Used 2')
        ->assertDontSee('Unused');
});

test('non-owner cannot access owner statuses', function () {
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );
    $agent = User::factory()->create(['role_id' => $agentRole->id]);

    $response = $this->actingAs($agent)
        ->get(route('owner.statuses'));

    $response->assertForbidden();
});


