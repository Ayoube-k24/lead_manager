<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Ensure roles exist
    \App\Models\Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    \App\Models\Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
    \App\Models\Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->role()->associate(\App\Models\Role::where('slug', 'super_admin')->first());
    $this->superAdmin->save();

    $this->callCenter = CallCenter::factory()->create();
    $this->owner = User::factory()->create([
        'call_center_id' => $this->callCenter->id,
    ]);
    $this->owner->role()->associate(\App\Models\Role::where('slug', 'call_center_owner')->first());
    $this->owner->save();
});

it('displays statistics page for super admin', function () {
    Lead::factory()->count(10)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Statistiques Avancées');
});

it('displays statistics page for call center owner', function () {
    Lead::factory()->count(5)->create([
        'call_center_id' => $this->callCenter->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Statistiques du Centre d\'Appels');
});

it('shows conversion rate in statistics', function () {
    Lead::factory()->count(10)->create(['status' => 'confirmed']);
    Lead::factory()->count(5)->create(['status' => 'rejected']);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Taux de Conversion');
});

it('shows agent performance for call center owner', function () {
    $agent = User::factory()->create([
        'call_center_id' => $this->callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    Lead::factory()->count(5)->create([
        'call_center_id' => $this->callCenter->id,
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Performance des agents');
});

it('prevents unauthorized access to statistics pages', function () {
    $agent = User::factory()->create([
        'call_center_id' => $this->callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    $response = $this->actingAs($agent)
        ->get(route('admin.statistics'));

    $response->assertForbidden();
});

it('displays leads needing attention', function () {
    Lead::factory()->create([
        'status' => 'email_confirmed',
        'email_confirmed_at' => now()->subHours(50),
        'called_at' => null,
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Leads nécessitant une attention');
});

it('displays underperforming agents', function () {
    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    // Create agent with low conversion rate
    Lead::factory()->count(12)->create([
        'assigned_to' => $agent->id,
        'status' => 'confirmed',
    ]);
    Lead::factory()->count(8)->create([
        'assigned_to' => $agent->id,
        'status' => 'rejected',
    ]);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics'));

    $response->assertSuccessful();
    $response->assertSee('Agents sous-performants');
});
