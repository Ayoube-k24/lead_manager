<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\SmtpProfile;
use App\Models\User;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AgentEmailService
{
    /**
     * Send email from agent to lead.
     */
    public function sendEmail(
        Lead $lead,
        User $agent,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?int $emailSubjectId = null,
        ?\Illuminate\Http\UploadedFile $attachment = null
    ): bool {
        // Get SMTP profile from lead's form
        $form = $lead->form;
        if (! $form) {
            Log::error('Lead has no form associated', ['lead_id' => $lead->id]);

            return false;
        }

        $smtpProfile = $form->smtpProfile;
        if (! $smtpProfile || ! $smtpProfile->is_active) {
            Log::error('No active SMTP profile found for lead', [
                'lead_id' => $lead->id,
                'form_id' => $form->id,
            ]);

            return false;
        }

        // Store attachment if provided
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        if ($attachment) {
            try {
                $attachmentPath = $attachment->store('email-attachments', 'private');
                $attachmentName = $attachment->getClientOriginalName();
                $attachmentMime = $attachment->getMimeType();
            } catch (\Exception $e) {
                Log::error('Failed to store email attachment', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        }

        // Get SMTP password
        $password = $smtpProfile->password;
        if (empty($password)) {
            Log::error('SMTP profile has no password', [
                'lead_id' => $lead->id,
                'smtp_profile_id' => $smtpProfile->id,
            ]);

            return false;
        }

        // Configure mailer
        $mailer = $this->configureMailer($smtpProfile, $password);

        try {
            // Send email
            $mailer->send([], [], function ($message) use ($lead, $smtpProfile, $subject, $bodyHtml, $bodyText, $attachmentPath, $attachmentName) {
                $message->to($lead->email)
                    ->subject($subject)
                    ->from($smtpProfile->from_address, $smtpProfile->from_name)
                    ->html($bodyHtml);

                if (! empty($bodyText)) {
                    $message->text($bodyText);
                }

                // Attach file if provided
                if ($attachmentPath) {
                    $fullPath = Storage::disk('private')->path($attachmentPath);
                    if (file_exists($fullPath)) {
                        $message->attach($fullPath, [
                            'as' => $attachmentName,
                        ]);
                    }
                }
            });

            // Save email record
            LeadEmail::create([
                'lead_id' => $lead->id,
                'user_id' => $agent->id,
                'email_subject_id' => $emailSubjectId,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'body_text' => $bodyText,
                'attachment_path' => $attachmentPath,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $attachmentMime,
                'sent_at' => now(),
            ]);

            Log::info('Agent email sent successfully', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'email' => $lead->email,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Exception $e) {
            // Delete attachment if email sending failed
            if ($attachmentPath && Storage::disk('private')->exists($attachmentPath)) {
                Storage::disk('private')->delete($attachmentPath);
            }

            Log::error('Failed to send agent email', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'email' => $lead->email,
                'error' => $e->getMessage(),
                'error_trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Configure mailer with SMTP profile.
     */
    protected function configureMailer(SmtpProfile $smtpProfile, string $password): Mailer
    {
        $config = [
            'transport' => 'smtp',
            'host' => $smtpProfile->host,
            'port' => $smtpProfile->port,
            'encryption' => $smtpProfile->encryption,
            'username' => $smtpProfile->username,
            'password' => $password,
            'timeout' => 30,
        ];

        Config::set('mail.mailers.agent_smtp', $config);

        return app('mail.manager')->mailer('agent_smtp');
    }
}
