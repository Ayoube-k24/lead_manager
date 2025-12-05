<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('DistributeUnassignedLeads Command', function () {
    test('distributes unassigned leads', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = \App\Models\Form::factory()->create(['call_center_id' => $callCenter->id]);
        // Create lead without events to prevent observer from auto-assigning
        $lead = Lead::withoutEvents(function () use ($form, $callCenter) {
            return Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
                'assigned_to' => null,
                'email_confirmed_at' => now(),
            ]);
        });

        $this->artisan('leads:distribute-unassigned')
            ->assertSuccessful()
            ->expectsOutput('Trouvé 1 lead(s) à distribuer.');

        expect(Lead::whereNotNull('assigned_to')->count())->toBe(1);
    });

    test('respects limit option', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = \App\Models\Form::factory()->create(['call_center_id' => $callCenter->id]);
        // Create leads without events to prevent observer from auto-assigning
        $leads = Lead::withoutEvents(function () use ($form, $callCenter) {
            return Lead::factory()->count(10)->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
                'assigned_to' => null,
                'email_confirmed_at' => now(),
            ]);
        });

        $this->artisan('leads:distribute-unassigned', ['--limit' => 5])
            ->assertSuccessful();

        // Should only process 5 leads (limit is applied)
        expect(Lead::whereNotNull('assigned_to')->count())->toBe(5);
    });

    test('skips leads from manual distribution call centers', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);

        Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'assigned_to' => null,
        ]);

        $this->artisan('leads:distribute-unassigned')
            ->assertSuccessful()
            ->expectsOutput('Aucun lead à distribuer trouvé.');

        expect(Lead::whereNotNull('assigned_to')->count())->toBe(0);
    });

    test('only processes email_confirmed and pending_call leads', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'assigned_to' => null,
        ]);
        Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'assigned_to' => null,
        ]);
        $form = \App\Models\Form::factory()->create(['call_center_id' => $callCenter->id]);
        // Create lead without events to prevent observer from auto-assigning
        $lead = Lead::withoutEvents(function () use ($form, $callCenter) {
            return Lead::factory()->create([
                'form_id' => $form->id,
                'call_center_id' => $callCenter->id,
                'status' => 'email_confirmed',
                'assigned_to' => null,
                'email_confirmed_at' => now(),
            ]);
        });

        $this->artisan('leads:distribute-unassigned')
            ->assertSuccessful();

        // Should only process email_confirmed lead
        expect(Lead::whereNotNull('assigned_to')->count())->toBe(1);
    });
});
