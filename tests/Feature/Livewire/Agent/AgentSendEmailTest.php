<?php

declare(strict_types=1);

use App\Models\EmailSubject;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Volt;

describe('Agent Send Email', function () {
    beforeEach(function () {
        Mail::fake();
    });

    test('agent can open email modal', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->call('openEmailModal')
            ->assertSet('showEmailModal', true);
    });

    test('agent can select email subject and it pre-fills form', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);
        $emailSubject = EmailSubject::factory()->create([
            'subject' => 'Devis mutuelle santé',
            'default_template_html' => '<p>Template HTML</p>',
        ]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->call('openEmailModal')
            ->set('selectedEmailSubjectId', $emailSubject->id)
            ->assertSet('emailSubject', 'Devis mutuelle santé')
            ->assertSet('emailBody', '<p>Template HTML</p>');
    });

    test('agent can send email successfully', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
        $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'assigned_to' => $agent->id,
            'email' => 'lead@example.com',
        ]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', '<p>Test Body</p>')
            ->call('sendEmail')
            ->assertSet('showEmailModal', false)
            ->assertDispatched('email-sent');

        // Verify email was sent
        Mail::assertSent(function ($mail) use ($lead) {
            return $mail->hasTo($lead->email);
        });

        // Verify email record was created
        expect(LeadEmail::where('lead_id', $lead->id)->exists())->toBeTrue();
    });

    test('agent cannot send email to lead not assigned to them', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $otherAgent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $otherAgent->id]);

        $response = $this->actingAs($agent)->get(route('agent.leads.show', $lead));

        $response->assertForbidden();
    });

    test('agent email validation works', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->set('emailSubject', '')
            ->set('emailBody', '')
            ->call('sendEmail')
            ->assertHasErrors(['emailSubject', 'emailBody']);
    });

    test('agent can toggle email preview', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->call('openEmailModal')
            ->call('toggleEmailPreview')
            ->assertSet('showEmailPreview', true)
            ->call('toggleEmailPreview')
            ->assertSet('showEmailPreview', false);
    });

    test('agent can switch between HTML and visual editor modes', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $lead = Lead::factory()->create(['assigned_to' => $agent->id]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->call('openEmailModal')
            ->set('emailEditorMode', 'visual')
            ->assertSet('emailEditorMode', 'visual')
            ->set('emailEditorMode', 'html')
            ->assertSet('emailEditorMode', 'html');
    });

    test('agent email uses SMTP from lead form', function () {
        $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
        $agent = User::factory()->withoutTwoFactor()->create([
            'role_id' => $agentRole->id,
            'is_active' => true,
        ]);

        $smtpProfile = SmtpProfile::factory()->create([
            'is_active' => true,
            'from_address' => 'form@example.com',
            'from_name' => 'Form Company',
        ]);

        $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
        $lead = Lead::factory()->create([
            'form_id' => $form->id,
            'assigned_to' => $agent->id,
            'email' => 'lead@example.com',
        ]);

        Volt::test('agent.leads.show', ['lead' => $lead])
            ->actingAs($agent)
            ->set('emailSubject', 'Test Subject')
            ->set('emailBody', '<p>Test Body</p>')
            ->call('sendEmail');

        // Verify email uses form's SMTP
        Mail::assertSent(function ($mail) use ($smtpProfile) {
            return $mail->hasFrom($smtpProfile->from_address, $smtpProfile->from_name);
        });
    });
});

