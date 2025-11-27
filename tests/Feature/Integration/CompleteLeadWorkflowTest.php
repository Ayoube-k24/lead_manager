<?php

declare(strict_types=1);

use App\Events\LeadCreated;
use App\Jobs\SendLeadConfirmationEmail;
use App\Models\CallCenter;
use App\Models\EmailTemplate;
use App\Models\Form;
use App\Models\Lead;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    require_once __DIR__.'/../Sprint1/EnsureMigrationsRun.php';
    ensureMigrationsRun();
});

describe('Complete Lead Workflow - Form Submission to Agent Assignment', function () {
    test('complete workflow from form submission to lead assignment', function () {
        // Arrange
        Event::fake();
        Queue::fake();

        $callCenter = CallCenter::factory()->create(['distribution_method' => 'round_robin']);
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create([
            'is_active' => true,
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
                ['name' => 'phone', 'type' => 'tel', 'required' => false],
            ],
        ]);

        // Act - Step 1: Submit form
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        // Assert - Step 1: Form submission
        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'lead_id']);

        $lead = Lead::find($response->json('lead_id'));
        expect($lead)->not->toBeNull()
            ->and($lead->status)->toBe('pending_email')
            ->and($lead->email)->toBe('john@example.com')
            ->and($lead->call_center_id)->toBe($callCenter->id)
            ->and($lead->assigned_to)->toBeNull();

        // Assert - Events and Jobs
        Event::assertDispatched(LeadCreated::class);
        Queue::assertPushed(SendLeadConfirmationEmail::class);

        // Act - Step 2: Confirm email
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert - Step 2: Email confirmation
        $lead->refresh();
        expect($lead->email_confirmed_at)->not->toBeNull()
            ->and($lead->status)->toBe('email_confirmed')
            ->and($lead->assigned_to)->toBe($agent->id); // Auto-assigned by observer

        // Act - Step 3: Agent updates status after call
        $this->actingAs($agent)
            ->post(route('agent.leads.show', $lead), [
                'status' => 'confirmed',
                'comment' => 'Lead intéressé, demande un rappel',
            ]);

        // Assert - Step 3: Status update
        $lead->refresh();
        expect($lead->status)->toBe('confirmed')
            ->and($lead->call_comment)->toBe('Lead intéressé, demande un rappel')
            ->and($lead->called_at)->not->toBeNull();
    });

    test('workflow handles email confirmation failure gracefully', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit form
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $lead = Lead::find($response->json('lead_id'));

        // Act - Try to confirm with invalid token
        $response = $this->get(route('leads.confirm-email', 'invalid-token'));

        // Assert
        $response->assertStatus(200)
            ->assertViewIs('leads.confirmation-error');

        $lead->refresh();
        expect($lead->email_confirmed_at)->toBeNull()
            ->and($lead->status)->toBe('pending_email');
    });

    test('workflow handles manual assignment when distribution is manual', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create(['distribution_method' => 'manual']);
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $ownerRole = Role::firstOrCreate(
            ['slug' => 'call_center_owner'],
            ['name' => 'Call Center Owner', 'slug' => 'call_center_owner']
        );
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'call_center_id' => $callCenter->id,
        ]);

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create([
            'is_active' => true,
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit form
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $lead = Lead::find($response->json('lead_id'));

        // Act - Confirm email
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert - Lead not auto-assigned in manual mode
        $lead->refresh();
        expect($lead->status)->toBe('email_confirmed')
            ->and($lead->assigned_to)->toBeNull();

        // Act - Owner manually assigns lead
        $this->actingAs($owner)
            ->post(route('owner.leads.assign', $lead), [
                'agent_id' => $agent->id,
            ]);

        // Assert - Lead assigned
        $lead->refresh();
        expect($lead->assigned_to)->toBe($agent->id)
            ->and($lead->status)->toBe('pending_call');
    });
});

describe('Complete Lead Workflow - Multi-Step Validation', function () {
    test('validates data integrity throughout workflow', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();

        $form = Form::factory()->create([
            'is_active' => true,
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'name', 'type' => 'text', 'required' => true],
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit form
        $response = $this->postJson(route('forms.submit', $form), [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $lead = Lead::find($response->json('lead_id'));

        // Assert - Data integrity at submission
        expect($lead->data['name'])->toBe('Jane Doe')
            ->and($lead->data['email'])->toBe('jane@example.com')
            ->and($lead->email)->toBe('jane@example.com')
            ->and($lead->form_id)->toBe($form->id)
            ->and($lead->call_center_id)->toBe($callCenter->id);

        // Act - Confirm email
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert - Data integrity after confirmation
        $lead->refresh();
        expect($lead->data['name'])->toBe('Jane Doe')
            ->and($lead->data['email'])->toBe('jane@example.com')
            ->and($lead->email)->toBe('jane@example.com')
            ->and($lead->email_confirmed_at)->not->toBeNull();
    });

    test('maintains audit trail throughout workflow', function () {
        // Arrange
        $callCenter = CallCenter::factory()->create();
        $smtpProfile = SmtpProfile::factory()->create();
        $emailTemplate = EmailTemplate::factory()->create();
        $agentRole = Role::firstOrCreate(
            ['slug' => 'agent'],
            ['name' => 'Agent', 'slug' => 'agent']
        );

        $agent = User::factory()->create([
            'role_id' => $agentRole->id,
            'call_center_id' => $callCenter->id,
            'is_active' => true,
        ]);

        $form = Form::factory()->create([
            'is_active' => true,
            'call_center_id' => $callCenter->id,
            'smtp_profile_id' => $smtpProfile->id,
            'email_template_id' => $emailTemplate->id,
            'fields' => [
                ['name' => 'email', 'type' => 'email', 'required' => true],
            ],
        ]);

        // Act - Submit form
        $response = $this->postJson(route('forms.submit', $form), [
            'email' => 'test@example.com',
        ]);

        $lead = Lead::find($response->json('lead_id'));

        // Assert - Audit log for lead creation
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'lead.created',
            'subject_id' => $lead->id,
            'subject_type' => Lead::class,
        ]);

        // Act - Confirm email
        $this->get(route('leads.confirm-email', $lead->email_confirmation_token));

        // Assert - Audit log for status update
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'lead.status_updated',
            'subject_id' => $lead->id,
            'properties->old_status' => 'pending_email',
            'properties->new_status' => 'email_confirmed',
        ]);

        // Act - Agent updates status
        $this->actingAs($agent)
            ->post(route('agent.leads.show', $lead), [
                'status' => 'confirmed',
            ]);

        // Assert - Audit log for status update
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'lead.status_updated',
            'subject_id' => $lead->id,
            'properties->old_status' => 'pending_call',
            'properties->new_status' => 'confirmed',
        ]);
    });
});
