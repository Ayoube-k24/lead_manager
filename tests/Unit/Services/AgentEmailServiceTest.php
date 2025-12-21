<?php

declare(strict_types=1);

use App\Models\EmailSubject;
use App\Models\Form;
use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\Role;
use App\Models\SmtpProfile;
use App\Models\User;
use App\Services\AgentEmailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

describe('AgentEmailService', function () {
    beforeEach(function () {
        $this->service = new AgentEmailService;
        Mail::fake();
        Storage::fake('private');
    });

    describe('sendEmail', function () {
        test('sends email successfully with SMTP from form', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form@example.com',
                'from_name' => 'Form Company',
            ]);

            $form = Form::factory()->create([
                'smtp_profile_id' => $smtpProfile->id,
            ]);

            $lead = Lead::factory()->create([
                'form_id' => $form->id,
                'email' => 'lead@example.com',
            ]);

            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>',
                'Test Text'
            );

            expect($result)->toBeTrue();

            // Verify email was sent
            Mail::assertSent(function (\Illuminate\Mail\SentMessage $mail) use ($lead, $smtpProfile) {
                return $mail->hasTo($lead->email)
                    && $mail->hasFrom($smtpProfile->from_address, $smtpProfile->from_name);
            });

            // Verify email record was created
            $leadEmail = LeadEmail::where('lead_id', $lead->id)->first();
            expect($leadEmail)->not->toBeNull()
                ->and($leadEmail->subject)->toBe('Test Subject')
                ->and($leadEmail->body_html)->toBe('<p>Test HTML</p>')
                ->and($leadEmail->body_text)->toBe('Test Text')
                ->and($leadEmail->user_id)->toBe($agent->id)
                ->and($leadEmail->sent_at)->not->toBeNull();
        });

        test('sends email with attachment', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $file = UploadedFile::fake()->create('document.pdf', 100);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>',
                null,
                null,
                $file
            );

            expect($result)->toBeTrue();

            // Verify attachment was stored
            $leadEmail = LeadEmail::where('lead_id', $lead->id)->first();
            expect($leadEmail->hasAttachment())->toBeTrue()
                ->and($leadEmail->attachment_name)->toBe('document.pdf')
                ->and($leadEmail->attachment_mime)->toBe('application/pdf')
                ->and(Storage::disk('private')->exists($leadEmail->attachment_path))->toBeTrue();
        });

        test('associates email subject when provided', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => true]);
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);
            $emailSubject = EmailSubject::factory()->create();

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>',
                null,
                $emailSubject->id
            );

            expect($result)->toBeTrue();

            $leadEmail = LeadEmail::where('lead_id', $lead->id)->first();
            expect($leadEmail->email_subject_id)->toBe($emailSubject->id);
        });

        test('returns false when lead has no form', function () {
            $lead = Lead::factory()->create(['form_id' => null]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>'
            );

            expect($result)->toBeFalse();
            Mail::assertNothingSent();
        });

        test('returns false when form has no SMTP profile', function () {
            $form = Form::factory()->create(['smtp_profile_id' => null]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>'
            );

            expect($result)->toBeFalse();
            Mail::assertNothingSent();
        });

        test('returns false when SMTP profile is inactive', function () {
            $smtpProfile = SmtpProfile::factory()->create(['is_active' => false]);
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>'
            );

            expect($result)->toBeFalse();
            Mail::assertNothingSent();
        });

        test('returns false when SMTP profile has no password', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'password' => null,
            ]);
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>'
            );

            expect($result)->toBeFalse();
            Mail::assertNothingSent();
        });

        test('deletes attachment when email sending fails', function () {
            $smtpProfile = SmtpProfile::factory()->create([
                'is_active' => true,
                'password' => null, // This will cause failure
            ]);
            $form = Form::factory()->create(['smtp_profile_id' => $smtpProfile->id]);
            $lead = Lead::factory()->create(['form_id' => $form->id]);
            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $file = UploadedFile::fake()->create('document.pdf', 100);

            $result = $this->service->sendEmail(
                $lead,
                $agent,
                'Test Subject',
                '<p>Test HTML</p>',
                null,
                null,
                $file
            );

            expect($result)->toBeFalse();

            // Verify attachment was not stored (or was deleted)
            $files = Storage::disk('private')->allFiles('email-attachments');
            expect($files)->toBeEmpty();
        });

        test('uses SMTP profile from lead form', function () {
            $smtpProfile1 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form1@example.com',
            ]);
            $smtpProfile2 = SmtpProfile::factory()->create([
                'is_active' => true,
                'from_address' => 'form2@example.com',
            ]);

            $form1 = Form::factory()->create(['smtp_profile_id' => $smtpProfile1->id]);
            $form2 = Form::factory()->create(['smtp_profile_id' => $smtpProfile2->id]);

            $lead1 = Lead::factory()->create(['form_id' => $form1->id]);
            $lead2 = Lead::factory()->create(['form_id' => $form2->id]);

            $agentRole = Role::firstOrCreate(['slug' => 'agent'], ['name' => 'Agent']);
            $agent = User::factory()->create(['role_id' => $agentRole->id]);

            $this->service->sendEmail($lead1, $agent, 'Subject', '<p>Body</p>');
            $this->service->sendEmail($lead2, $agent, 'Subject', '<p>Body</p>');

            // Verify each email uses its form's SMTP
            Mail::assertSent(function (\Illuminate\Mail\SentMessage $mail) use ($lead1, $smtpProfile1) {
                return $mail->hasTo($lead1->email)
                    && $mail->hasFrom($smtpProfile1->from_address);
            });

            Mail::assertSent(function (\Illuminate\Mail\SentMessage $mail) use ($lead2, $smtpProfile2) {
                return $mail->hasTo($lead2->email)
                    && $mail->hasFrom($smtpProfile2->from_address);
            });
        });
    });
});






