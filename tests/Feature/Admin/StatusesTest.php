<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Role;
use App\Models\User;
use Livewire\Volt\Volt;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();

    $this->adminRole = Role::firstOrCreate(
        ['slug' => 'super_admin'],
        ['name' => 'Super Admin', 'slug' => 'super_admin']
    );

    $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
});

test('admin can view statuses list', function () {
    LeadStatus::create(['slug' => 'test1', 'name' => 'Test 1', 'color' => '#FF0000']);
    LeadStatus::create(['slug' => 'test2', 'name' => 'Test 2', 'color' => '#00FF00']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.statuses'));

    $response->assertSuccessful()
        ->assertSee('Test 1')
        ->assertSee('Test 2');
});

test('admin can create a status', function () {
    $response = $this->actingAs($this->admin)
        ->get(route('admin.statuses.create'));

    $response->assertSuccessful()
        ->assertSee('Créer un statut');
});

test('admin can store a new status', function () {
    Volt::test('admin.statuses.create')
        ->actingAs($this->admin)
        ->set('slug', 'new_status')
        ->set('name', 'New Status')
        ->set('color', '#FF0000')
        ->set('description', 'Test description')
        ->set('is_active', true)
        ->set('is_final', false)
        ->set('can_be_set_after_call', true)
        ->call('store')
        ->assertRedirect(route('admin.statuses'));

    $this->assertDatabaseHas('lead_statuses', [
        'slug' => 'new_status',
        'name' => 'New Status',
        'color' => '#FF0000',
        'is_active' => true,
        'is_final' => false,
        'can_be_set_after_call' => true,
    ]);
});

test('admin can edit a status', function () {
    $status = LeadStatus::create([
        'slug' => 'edit_me',
        'name' => 'Edit Me',
        'color' => '#FF0000',
    ]);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.statuses.edit', $status));

    $response->assertSuccessful()
        ->assertSee('Modifier le statut');
});

test('admin can update a status', function () {
    $status = LeadStatus::create([
        'slug' => 'update_me',
        'name' => 'Update Me',
        'color' => '#FF0000',
    ]);

    Volt::test('admin.statuses.edit', ['status' => $status])
        ->actingAs($this->admin)
        ->set('name', 'Updated Name')
        ->set('color', '#00FF00')
        ->set('description', 'Updated description')
        ->call('update')
        ->assertRedirect(route('admin.statuses'));

    $this->assertDatabaseHas('lead_statuses', [
        'id' => $status->id,
        'name' => 'Updated Name',
        'color' => '#00FF00',
        'description' => 'Updated description',
    ]);
});

test('admin can delete a non-system status', function () {
    $status = LeadStatus::create([
        'slug' => 'delete_me',
        'name' => 'Delete Me',
        'color' => '#FF0000',
        'is_system' => false,
    ]);

    Volt::test('admin.statuses')
        ->actingAs($this->admin)
        ->call('delete', $status)
        ->assertSuccessful();

    $this->assertDatabaseMissing('lead_statuses', ['id' => $status->id]);
});

test('admin cannot delete a system status', function () {
    $status = LeadStatus::create([
        'slug' => 'system_status',
        'name' => 'System Status',
        'color' => '#FF0000',
        'is_system' => true,
    ]);

    Volt::test('admin.statuses')
        ->actingAs($this->admin)
        ->call('delete', $status);

    $this->assertDatabaseHas('lead_statuses', ['id' => $status->id]);
});

test('admin can search statuses', function () {
    LeadStatus::create(['slug' => 'found', 'name' => 'Found Status', 'color' => '#FF0000']);
    LeadStatus::create(['slug' => 'hidden', 'name' => 'Hidden Status', 'color' => '#00FF00']);

    Volt::test('admin.statuses')
        ->actingAs($this->admin)
        ->set('search', 'Found')
        ->assertSee('Found Status')
        ->assertDontSee('Hidden Status');
});

test('non-admin cannot access admin statuses', function () {
    $agentRole = Role::firstOrCreate(
        ['slug' => 'agent'],
        ['name' => 'Agent', 'slug' => 'agent']
    );
    $agent = User::factory()->create(['role_id' => $agentRole->id]);

    $response = $this->actingAs($agent)
        ->get(route('admin.statuses'));

    $response->assertForbidden();
});

