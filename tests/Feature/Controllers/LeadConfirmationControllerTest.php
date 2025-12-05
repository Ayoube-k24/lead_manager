<?php

declare(strict_types=1);

use App\Models\CallCenter;
use App\Models\Lead;
use Illuminate\Support\Str;

describe('LeadConfirmationController - Confirm', function () {
    test('returns confirmation success view with valid token', function () {
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-success');
    });

    test('returns confirmation error view with invalid token', function () {
        $response = $this->get(route('leads.confirm-email', 'invalid-token'));

        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');
    });

    test('returns confirmation error view with expired token', function () {
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->subHours(1),
            'email_confirmed_at' => null,
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');
    });

    test('returns success view if already confirmed', function () {
        $lead = Lead::factory()->create([
            'status' => 'email_confirmed',
            'email_confirmation_token' => Str::random(64),
            'email_confirmed_at' => now()->subHours(1),
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-success');
    });

    test('associates call center id from form if missing', function () {
        $callCenter = CallCenter::factory()->create();
        $form = \App\Models\Form::factory()->create([
            'call_center_id' => $callCenter->id,
        ]);

        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
            'call_center_id' => null,
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200);

        $lead->refresh();
        expect($lead->call_center_id)->toBe($callCenter->id);
    });

    test('updates status to email_confirmed after confirmation', function () {
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200);

        $lead->refresh();
        expect($lead->status)->toBe('email_confirmed')
            ->and($lead->email_confirmed_at)->not->toBeNull();
    });

    test('triggers automatic distribution via observer after confirmation', function () {
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = \App\Models\Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = \App\Models\User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create([
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        $response->assertStatus(200);

        $lead->refresh();
        expect($lead->status)->toBe('email_confirmed')
            ->and($lead->assigned_to)->not->toBeNull()
            ->and($lead->assigned_to)->toBe($agent->id);
    });
});
