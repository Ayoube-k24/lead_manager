<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadEmail;
use App\Models\SmtpProfile;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        if (!$form) {
            Log::error('Lead has no form associated', ['lead_id' => $lead->id]);

            return false;
        }

        $smtpProfile = $form->smtpProfile;
        if (!$smtpProfile || !$smtpProfile->is_active) {
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

        // Check if password exists but cannot be decrypted BEFORE trying to access it
        // This prevents the accessor from logging errors unnecessarily
        if ($smtpProfile->hasEncryptedPasswordButCannotDecrypt()) {
            Log::error('SMTP password cannot be decrypted - APP_KEY may have changed', [
                'lead_id' => $lead->id,
                'smtp_profile_id' => $smtpProfile->id,
                'smtp_profile_name' => $smtpProfile->name,
                'action_required' => 'Please update the SMTP profile password in the admin panel',
            ]);

            // Store a specific error message in session for the user
            session()->flash('email-error', __('Erreur de configuration SMTP : le mot de passe ne peut pas être déchiffré. Veuillez contacter l\'administrateur pour mettre à jour le profil SMTP.'));
            session()->flash('email-error-type', 'smtp_decryption_error');

            return false;
        }

        // Get SMTP password (only after verifying it can be decrypted)
        $password = $smtpProfile->password;

        if (empty($password)) {
            Log::error('SMTP profile has no password', [
                'lead_id' => $lead->id,
                'smtp_profile_id' => $smtpProfile->id,
                'smtp_profile_name' => $smtpProfile->name,
            ]);

            return false;
        }

        // Log configuration for debugging (without password)
        Log::debug('Sending agent email', [
            'lead_id' => $lead->id,
            'agent_id' => $agent->id,
            'email' => $lead->email,
            'smtp_host' => $smtpProfile->host,
            'smtp_port' => $smtpProfile->port,
            'smtp_username' => $smtpProfile->username,
            'smtp_encryption' => $smtpProfile->encryption,
            'from_address' => $smtpProfile->from_address,
            'from_name' => $smtpProfile->from_name,
            'password_set' => !empty($password),
        ]);

        // Configure mailer
        $this->configureMailer($smtpProfile, $password);

        try {
            // Send email
            Mail::send([], [], function ($message) use ($lead, $smtpProfile, $subject, $bodyHtml, $bodyText, $attachmentPath, $attachmentName) {
                $message->to($lead->email)
                    ->subject($subject)
                    ->from($smtpProfile->from_address, $smtpProfile->from_name)
                    ->html($bodyHtml);

                if (!empty($bodyText)) {
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
                'smtp_host' => $smtpProfile->host,
            ]);

            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $isAuthError = $this->isAuthenticationError($errorMessage);

            // Delete attachment if email sending failed
            if ($attachmentPath && Storage::disk('private')->exists($attachmentPath)) {
                Storage::disk('private')->delete($attachmentPath);
            }

            Log::error('Failed to send agent email', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'email' => $lead->email,
                'error' => $errorMessage,
                'error_trace' => $e->getTraceAsString(),
                'smtp_host' => $smtpProfile->host,
                'smtp_port' => $smtpProfile->port,
                'smtp_username' => $smtpProfile->username,
                'is_authentication_error' => $isAuthError,
            ]);

            // If it's an authentication error, try to use an alternative SMTP profile
            if ($isAuthError) {
                Log::warning('SMTP authentication failed, attempting fallback to alternative profile', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'failed_smtp_profile_id' => $smtpProfile->id,
                    'smtp_host' => $smtpProfile->host,
                    'smtp_username' => $smtpProfile->username,
                ]);

                // Try to send with an alternative active SMTP profile
                $alternativeSent = $this->sendWithAlternativeProfile(
                    $lead,
                    $agent,
                    $smtpProfile,
                    $subject,
                    $bodyHtml,
                    $bodyText,
                    $emailSubjectId,
                    $attachmentPath,
                    $attachmentName,
                    $attachmentMime
                );

                if ($alternativeSent) {
                    return true;
                }

                // If no alternative worked, log that the SMTP profile should be checked
                Log::error('All SMTP profiles failed for agent email', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'original_smtp_profile_id' => $smtpProfile->id,
                ]);
            }

            return false;
        }
    }

    /**
     * Configure mailer with SMTP profile.
     */
    protected function configureMailer(SmtpProfile $smtpProfile, string $password): void
    {
        // If Mail is faked (for testing), don't change config
        if (Mail::getFacadeRoot() instanceof \Illuminate\Support\Testing\Fakes\MailFake) {
            return;
        }

        // Configure the default SMTP mailer
        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => $smtpProfile->host,
            'port' => $smtpProfile->port,
            'encryption' => $smtpProfile->encryption === 'none' ? null : $smtpProfile->encryption,
            'username' => $smtpProfile->username,
            'password' => $password,
            'timeout' => null,
        ]);
    }

    /**
     * Check if the error is an authentication error (535).
     */
    protected function isAuthenticationError(string $errorMessage): bool
    {
        $errorLower = strtolower($errorMessage);

        return str_contains($errorLower, '535') ||
            str_contains($errorLower, 'authentication failed') ||
            str_contains($errorLower, 'authentication failure') ||
            str_contains($errorLower, 'invalid login') ||
            str_contains($errorLower, 'expected response code "235" but got code "535"');
    }

    /**
     * Try to send email with an alternative active SMTP profile.
     */
    protected function sendWithAlternativeProfile(
        Lead $lead,
        User $agent,
        SmtpProfile $failedProfile,
        string $subject,
        string $bodyHtml,
        ?string $bodyText,
        ?int $emailSubjectId,
        ?string $attachmentPath,
        ?string $attachmentName,
        ?string $attachmentMime
    ): bool {
        // Get all active SMTP profiles except the failed one
        $alternativeProfiles = SmtpProfile::where('is_active', true)
            ->where('id', '!=', $failedProfile->id)
            ->get();

        if ($alternativeProfiles->isEmpty()) {
            Log::warning('No alternative SMTP profiles available', [
                'lead_id' => $lead->id,
                'agent_id' => $agent->id,
                'failed_smtp_profile_id' => $failedProfile->id,
            ]);

            return false;
        }

        // Try each alternative profile
        foreach ($alternativeProfiles as $alternativeProfile) {
            $password = $alternativeProfile->password;

            // Check if password exists but cannot be decrypted
            if ($alternativeProfile->hasEncryptedPasswordButCannotDecrypt()) {
                Log::warning('Alternative SMTP profile password cannot be decrypted', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'smtp_profile_id' => $alternativeProfile->id,
                    'smtp_profile_name' => $alternativeProfile->name,
                ]);

                continue;
            }

            if (empty($password)) {
                Log::warning('Alternative SMTP profile has no password', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'smtp_profile_id' => $alternativeProfile->id,
                ]);

                continue;
            }

            try {
                $this->configureMailer($alternativeProfile, $password);

                Mail::send([], [], function ($message) use ($lead, $alternativeProfile, $subject, $bodyHtml, $bodyText, $attachmentPath, $attachmentName) {
                    $message->to($lead->email)
                        ->subject($subject)
                        ->from($alternativeProfile->from_address, $alternativeProfile->from_name)
                        ->html($bodyHtml);

                    if (!empty($bodyText)) {
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

                Log::info('Agent email sent successfully with alternative SMTP profile', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'email' => $lead->email,
                    'original_smtp_profile_id' => $failedProfile->id,
                    'alternative_smtp_profile_id' => $alternativeProfile->id,
                    'smtp_host' => $alternativeProfile->host,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::warning('Alternative SMTP profile also failed', [
                    'lead_id' => $lead->id,
                    'agent_id' => $agent->id,
                    'smtp_profile_id' => $alternativeProfile->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return false;
    }
}
