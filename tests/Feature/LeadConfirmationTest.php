<?php

declare(strict_types=1);

use App\Models\ActivityLog;
use App\Models\CallCenter;
use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Str;

describe('Lead Confirmation - Successful Confirmation', function () {
    test('lead can confirm email with valid token', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        // Act
        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-success');

        $lead->refresh();
        expect($lead->email_confirmed_at)->not->toBeNull()
            ->and($lead->status)->toBe('email_confirmed');
    });

    test('triggers distribution after email confirmation', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
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

        // Act
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert
        $lead->refresh();
        expect($lead->status)->toBe('email_confirmed')
            ->and($lead->assigned_to)->not->toBeNull()
            ->and($lead->assigned_to)->toBe($agent->id);
    });

    test('logs confirmation in audit trail', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        // Act
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert
        $log = ActivityLog::where('action', 'lead.status_updated')
            ->where('subject_id', $lead->id)
            ->where('properties->old_status', 'pending_email')
            ->where('properties->new_status', 'email_confirmed')
            ->first();

        expect($log)->not->toBeNull();
    });
});

describe('Lead Confirmation - Token Validation', function () {
    test('invalid token shows error page', function () {
        // Act
        $response = $this->get(route('leads.confirm-email', 'invalid-token'));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');
    });

    test('expired token shows error page', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->subHours(1),
            'email_confirmed_at' => null,
        ]);

        // Act
        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');

        $lead->refresh();
        expect($lead->email_confirmed_at)->toBeNull()
            ->and($lead->status)->toBe('pending_email');
    });

    test('null token shows error page', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => null,
            'email_confirmed_at' => null,
        ]);

        // Act
        $response = $this->get(route('leads.confirm-email', 'some-token'));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');
    });
});

describe('Lead Confirmation - Idempotence', function () {
    test('already confirmed email shows success message', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'email_confirmed',
            'email_confirmation_token' => Str::random(64),
            'email_confirmed_at' => now()->subHours(1),
        ]);

        // Act
        $response = $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-success');
    });

    test('multiple confirmations with same token are idempotent', function () {
        // Arrange
        $lead = Lead::factory()->create([
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'email_confirmed_at' => null,
        ]);

        // Act - Confirm multiple times
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));
        $firstConfirmation = $lead->fresh()->email_confirmed_at;

        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));
        $secondConfirmation = $lead->fresh()->email_confirmed_at;

        // Assert
        expect($firstConfirmation)->not->toBeNull()
            ->and($secondConfirmation)->not->toBeNull()
            ->and($lead->fresh()->status)->toBe('email_confirmed');
    });
});
