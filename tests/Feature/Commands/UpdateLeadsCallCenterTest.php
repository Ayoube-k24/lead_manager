<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;

describe('UpdateLeadsCallCenter Command', function () {
    test('updates leads with call_center_id from form', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => null,
            'assigned_to' => null,
        ]);

        $this->artisan('leads:update-call-center')
            ->assertSuccessful()
            ->expectsOutput('1 leads mis à jour avec succès.');

        expect($lead->fresh()->call_center_id)->toBe($callCenter->id);
    });

    test('distributes leads after updating call_center_id', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $role = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $role->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => null,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now(),
            'assigned_to' => null,
        ]);

        $this->artisan('leads:update-call-center')
            ->assertSuccessful();

        expect($lead->fresh()->call_center_id)->toBe($callCenter->id);
        expect($lead->fresh()->assigned_to)->toBe($agent->id);
    });

    test('warns about leads that cannot be updated', function () {
        $form = Form::factory()->create(['call_center_id' => null]);

        Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => null,
            'assigned_to' => null,
        ]);

        $this->artisan('leads:update-call-center')
            ->assertSuccessful()
            ->expectsOutput('1 leads ne peuvent pas être mis à jour car leur formulaire n\'a pas de centre d\'appel associé.');
    });

    test('returns success when no leads to update', function () {
        $this->artisan('leads:update-call-center')
            ->assertSuccessful()
            ->expectsOutput('0 leads mis à jour avec succès.');
    });
});
