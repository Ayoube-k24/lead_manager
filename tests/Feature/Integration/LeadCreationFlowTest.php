<?php

declare(strict_types=1);

use App\Events\LeadAssigned;
use App\Events\LeadCreated;
use App\Events\LeadEmailConfirmed;
use App\Jobs\SendLeadConfirmationEmail;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

describe('Lead Creation Flow - Complete Integration', function () {
    beforeEach(function () {
        Mail::fake();
        Queue::fake();
    });

    test('complete flow: form submission → email confirmation → distribution → assignment', function () {
        // Setup: Create call center, agent, form with SMTP and template
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);
        
        // Ensure role is loaded for isAgent() check
        $agent->load('role');
        
        // Verify agent setup
        expect($agent->isAgent())->toBeTrue()
            ->and($agent->is_active)->toBeTrue()
            ->and($agent->call_center_id)->toBe($callCenter->id);
        
        // Don't fake events - let the observer work naturally
        // We'll verify events after the fact by checking the database state

        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $emailTemplate = EmailTemplate::factory()->create([
            'subject' => 'Confirmez votre email',
            'body_html' => '<p>Cliquez <a href="{{confirmation_url}}">ici</a> pour confirmer.</p>',
        ]);

        $form = Form::factory()->create([
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'is_active' => true,
            'fields' => [
                [
                    'name' => 'name',
                    'type' => 'text',
                    'label' => 'Nom',
                    'required' => true,
                ],
                [
                    'name' => 'email',
                    'type' => 'email',
                    'label' => 'Email',
                    'required' => true,
                ],
            ],
        ]);

        // Step 1: Submit form
        $response = $this->postJson("/forms/{$form->uid}/submit", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertSuccessful();

        // Step 2: Verify lead was created
        $lead = Lead::where('email', 'john@example.com')->first();
        expect($lead)->not->toBeNull()
            ->and($lead->status)->toBe('pending_email')
            ->and($lead->form_id)->toBe($form->id)
            ->and($lead->call_center_id)->toBe($callCenter->id)
            ->and($lead->email_confirmation_token)->not->toBeNull()
            ->and($lead->assigned_to)->toBeNull();

        // Step 3: Verify jobs were dispatched (events are not faked, so we can't assert them)
        Queue::assertPushed(SendLeadConfirmationEmail::class, function ($job) use ($lead) {
            return $job->lead->id === $lead->id;
        });

        // Step 4: Verify score was calculated
        expect($lead->score)->not->toBeNull()
            ->and($lead->score_updated_at)->not->toBeNull();

        // Step 5: Verify agent can be found by distribution service
        // Temporarily set status to email_confirmed to test distribution
        $lead->load(['form', 'callCenter']);
        $distributionService = app(\App\Services\LeadDistributionService::class);
        
        // Test distribution with email_confirmed status (what observer will see)
        $lead->status = 'email_confirmed';
        $lead->saveQuietly(); // Save without triggering observer
        $testAgent = $distributionService->distributeLead($lead, $callCenter);
        expect($testAgent)->not->toBeNull()
            ->and($testAgent->id)->toBe($agent->id);
        
        // Reset status back to pending_email for the actual confirmation flow
        $lead->status = 'pending_email';
        $lead->saveQuietly();

        // Step 6: Confirm email (simulate clicking confirmation link)
        // Note: Event::fake() is active, but observer should still work for database updates
        $token = $lead->email_confirmation_token;
        $confirmResponse = $this->get("/leads/confirm-email/{$token}");

        $confirmResponse->assertSuccessful();

        // Step 7: Verify email was confirmed and lead was assigned
        // The observer should have automatically distributed the lead and updated status to pending_call
        $lead->refresh();
        
        // Debug output if test fails
        if ($lead->status !== 'pending_call' || !$lead->assigned_to) {
            \Log::info('Test Debug: Lead after confirmation', [
                'lead_id' => $lead->id,
                'status' => $lead->status,
                'email_confirmed_at' => $lead->email_confirmed_at,
                'assigned_to' => $lead->assigned_to,
                'call_center_id' => $lead->call_center_id,
                'form_id' => $lead->form_id,
                'form_call_center_id' => $lead->form?->call_center_id,
            ]);
        }
        
        expect($lead->email_confirmed_at)->not->toBeNull()
            ->and($lead->status)->toBe('pending_call')
            ->and($lead->assigned_to)->not->toBeNull()
            ->and($lead->assigned_to)->toBe($agent->id);

        // Step 8: Events were dispatched naturally (not faked, so we verify by database state)
        // The fact that the lead is assigned and status is pending_call confirms events worked
    });

    test('flow handles inactive form gracefully', function () {
        $form = Form::factory()->create(['is_active' => false]);

        $response = $this->postJson("/forms/{$form->uid}/submit", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Ce formulaire n\'est pas actif.',
            ]);

        expect(Lead::where('email', 'john@example.com')->exists())->toBeFalse();
    });

    test('flow handles form without SMTP profile', function () {
        $form = Form::factory()->create([
            'is_active' => true,
            'smtp_profile_id' => null,
        ]);

        $response = $this->postJson("/forms/{$form->uid}/submit", [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Ce formulaire n\'est pas configuré correctement.',
            ]);
    });

    test('flow handles invalid confirmation token', function () {
        $response = $this->get('/leads/confirm-email/invalid-token');

        $response->assertSuccessful(); // Returns view with error message
        expect(Lead::where('email_confirmation_token', 'invalid-token')->exists())->toBeFalse();
    });

    test('flow handles expired confirmation token', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'pending_email',
            'email_confirmation_token' => 'expired-token',
            'email_confirmation_token_expires_at' => now()->subDay(),
            'email_confirmed_at' => null, // Explicitly set to null
        ]);

        $response = $this->get('/leads/confirm-email/expired-token');

        $response->assertSuccessful(); // Returns view with error message
        $lead->refresh();
        expect($lead->email_confirmed_at)->toBeNull();
    });

    test('flow handles already confirmed email', function () {
        $callCenter = CallCenter::factory()->create();
        $form = Form::factory()->create(['call_center_id' => $callCenter->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'call_center_id' => $callCenter->id,
            'status' => 'email_confirmed',
            'email_confirmed_at' => now(),
            'email_confirmation_token' => 'already-confirmed-token',
            'email_confirmation_token_expires_at' => now()->addDay(),
        ]);

        $response = $this->get('/leads/confirm-email/already-confirmed-token');

        $response->assertSuccessful(); // Returns view with success message
        $lead->refresh();
        expect($lead->email_confirmed_at)->not->toBeNull();
    });
});

