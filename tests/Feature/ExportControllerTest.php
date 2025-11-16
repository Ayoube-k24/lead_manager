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

it('exports leads to CSV for super admin', function () {
    Lead::factory()->count(5)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.leads.export.csv'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $contentDisposition = $response->headers->get('Content-Disposition');
    expect($contentDisposition)->toContain('attachment')
        ->and($contentDisposition)->toContain('.csv');
});

it('exports leads to CSV for call center owner', function () {
    Lead::factory()->count(3)->create([
        'call_center_id' => $this->callCenter->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.leads.export.csv'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('filters leads by status when exporting CSV', function () {
    Lead::factory()->count(3)->create(['status' => 'confirmed']);
    Lead::factory()->count(2)->create(['status' => 'rejected']);

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.leads.export.csv', ['status' => 'confirmed']));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('exports statistics to CSV for super admin', function () {
    Lead::factory()->count(10)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics.export.csv'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('exports statistics to CSV for call center owner', function () {
    Lead::factory()->count(5)->create([
        'call_center_id' => $this->callCenter->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statistics.export.csv'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});

it('exports statistics to PDF for super admin', function () {
    Lead::factory()->count(10)->create();

    $response = $this->actingAs($this->superAdmin)
        ->get(route('admin.statistics.export.pdf'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('exports statistics to PDF for call center owner', function () {
    Lead::factory()->count(5)->create([
        'call_center_id' => $this->callCenter->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.statistics.export.pdf'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'application/pdf');
});

it('prevents unauthorized access to exports', function () {
    $agent = User::factory()->create([
        'call_center_id' => $this->callCenter->id,
    ]);
    $agent->role()->associate(\App\Models\Role::where('slug', 'agent')->first());
    $agent->save();

    $response = $this->actingAs($agent)
        ->get(route('admin.statistics.export.csv'));

    $response->assertForbidden();
});

it('only exports leads from own call center for owners', function () {
    $otherCallCenter = CallCenter::factory()->create();
    Lead::factory()->count(3)->create([
        'call_center_id' => $this->callCenter->id,
    ]);
    Lead::factory()->count(2)->create([
        'call_center_id' => $otherCallCenter->id,
    ]);

    $response = $this->actingAs($this->owner)
        ->get(route('owner.leads.export.csv'));

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
