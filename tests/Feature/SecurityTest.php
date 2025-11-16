<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['slug' => 'super_admin'], ['name' => 'Super Admin']);
    Role::firstOrCreate(['slug' => 'call_center_owner'], ['name' => 'Call Center Owner']);
    Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
});

it('logs form creation', function () {
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $this->actingAs($user);

    $form = Form::factory()->create();

    $log = ActivityLog::where('action', 'form.created')
        ->where('subject_id', $form->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->subject_type)->toBe(Form::class);
});

it('logs form update', function () {
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $this->actingAs($user);

    $form = Form::factory()->create();
    $form->name = 'Updated Name';
    $form->save();

    $log = ActivityLog::where('action', 'form.updated')
        ->where('subject_id', $form->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id);
});

it('logs lead status update', function () {
    $auditService = app(AuditService::class);
    $agent = User::factory()->create();
    $agent->role()->associate(Role::where('slug', 'agent')->first());
    $agent->save();

    $this->actingAs($agent);

    $lead = Lead::factory()->create([
        'assigned_to' => $agent->id,
        'status' => 'pending_call',
    ]);

    $lead->updateAfterCall('confirmed', 'Lead intéressé');

    $log = ActivityLog::where('action', 'lead.status_updated')
        ->where('subject_id', $lead->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old_status'])->toBe('pending_call')
        ->and($log->properties['new_status'])->toBe('confirmed');
});

it('logs lead assignment', function () {
    $auditService = app(AuditService::class);
    $callCenter = CallCenter::factory()->create();
    $agent = User::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);
    $agent->role()->associate(Role::where('slug', 'agent')->first());
    $agent->save();

    $lead = Lead::factory()->create([
        'call_center_id' => $callCenter->id,
    ]);

    $distributionService = app(\App\Services\LeadDistributionService::class);
    $distributionService->assignToAgent($lead, $agent);

    $log = ActivityLog::where('action', 'lead.assigned')
        ->where('subject_id', $lead->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['agent_id'])->toBe($agent->id);
});

it('logs agent creation', function () {
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'call_center_owner')->first());
    $user->save();

    $this->actingAs($user);

    $agent = User::factory()->create();

    $log = ActivityLog::where('action', 'agent.created')
        ->where('subject_id', $agent->id)
        ->first();

    expect($log)->not->toBeNull();
});

it('logs distribution method change', function () {
    $auditService = app(AuditService::class);
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'call_center_owner')->first());
    $user->save();

    $callCenter = CallCenter::factory()->create([
        'owner_id' => $user->id,
        'distribution_method' => 'round_robin',
    ]);
    $user->call_center_id = $callCenter->id;
    $user->save();

    $this->actingAs($user);

    $callCenter->distribution_method = 'weighted';
    $callCenter->save();

    $log = ActivityLog::where('action', 'distribution_method.changed')
        ->where('subject_id', $callCenter->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['old_method'])->toBe('round_robin')
        ->and($log->properties['new_method'])->toBe('weighted');
});

it('prevents unauthorized access to audit logs', function () {
    $agent = User::factory()->create();
    $agent->role()->associate(Role::where('slug', 'agent')->first());
    $agent->save();

    $response = $this->actingAs($agent)
        ->get(route('admin.audit-logs'));

    $response->assertForbidden();
});

it('allows super admin to access audit logs', function () {
    $admin = User::factory()->create();
    $admin->role()->associate(Role::where('slug', 'super_admin')->first());
    $admin->save();

    $response = $this->actingAs($admin)
        ->get(route('admin.audit-logs'));

    $response->assertSuccessful();
});

it('logs failed login attempts', function () {
    $auditService = app(AuditService::class);

    $auditService->logFailedLogin('test@example.com', 'Invalid password');

    $log = ActivityLog::where('action', 'auth.login_failed')
        ->where('properties->email', 'test@example.com')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->properties['reason'])->toContain('Invalid');
});

it('stores ip address and user agent in audit logs', function () {
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', 'super_admin')->first());
    $user->save();

    $this->actingAs($user);

    $form = Form::factory()->create();

    $log = ActivityLog::where('action', 'form.created')
        ->where('subject_id', $form->id)
        ->first();

    expect($log->ip_address)->not->toBeNull()
        ->and($log->user_agent)->not->toBeNull();
});
