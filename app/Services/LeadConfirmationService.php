<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LeadConfirmationService
{
    /**
     * Send confirmation email to a lead using the form's SMTP profile and email template.
     */
    public function sendConfirmationEmail(Lead $lead): bool
    {
        $form = $lead->form;

        if (! $form) {
            return false;
        }

        $smtpProfile = $form->smtpProfile;
        $emailTemplate = $form->emailTemplate;

        if (! $smtpProfile || ! $emailTemplate) {
            return false;
        }

        if (! $smtpProfile->is_active) {
            return false;
        }

        // Generate confirmation token if not exists
        if (! $lead->email_confirmation_token) {
            $lead->email_confirmation_token = Str::random(64);
        }

        // Always set expiration if not set or expired
        if (! $lead->email_confirmation_token_expires_at || $lead->email_confirmation_token_expires_at->isPast()) {
            $lead->email_confirmation_token_expires_at = now()->addHours(24);
        }

        // Save if token or expiration was updated
        if ($lead->isDirty(['email_confirmation_token', 'email_confirmation_token_expires_at'])) {
            $lead->save();
        }

        // Build confirmation URL
        $confirmationUrl = route('leads.confirm-email', [
            'token' => $lead->email_confirmation_token,
        ]);

        // Prepare template variables
        $variables = $this->prepareTemplateVariables($lead, $confirmationUrl);

        // Render email content
        $subject = $this->renderTemplate($emailTemplate->subject, $variables);
        $bodyHtml = $this->renderTemplate($emailTemplate->body_html, $variables);
        $bodyText = $this->renderTemplate($emailTemplate->body_text ?? '', $variables);

        // Get decrypted password
        $password = $smtpProfile->password;

        // Log configuration for debugging (without password)
        Log::debug('Sending confirmation email', [
            'lead_id' => $lead->id,
            'email' => $lead->email,
            'smtp_host' => $smtpProfile->host,
            'smtp_port' => $smtpProfile->port,
            'smtp_username' => $smtpProfile->username,
            'smtp_encryption' => $smtpProfile->encryption,
            'from_address' => $smtpProfile->from_address,
            'from_name' => $smtpProfile->from_name,
            'password_set' => ! empty($password),
            'password_length' => $password ? strlen($password) : 0,
        ]);

        // Check if password is available
        if (empty($password)) {
            Log::error('SMTP password is empty or could not be decrypted', [
                'lead_id' => $lead->id,
                'smtp_profile_id' => $smtpProfile->id,
            ]);

            return false;
        }

        // Configure mailer with SMTP profile
        $mailer = $this->configureMailer($smtpProfile, $password);

        try {
            // Send email synchronously (directly) without timeout restrictions
            // If sending fails, it will be queued for retry
            // Use raw() method to send HTML email
            $mailer->raw($bodyHtml, function ($message) use ($lead, $smtpProfile, $subject, $bodyText) {
                $message->to($lead->email)
                    ->subject($subject)
                    ->from($smtpProfile->from_address, $smtpProfile->from_name);

                // Add text version if available
                if (! empty($bodyText)) {
                    $message->text($bodyText);
                }
            });

            Log::info('Confirmation email sent successfully', [
                'lead_id' => $lead->id,
                'email' => $lead->email,
                'smtp_host' => $smtpProfile->host,
            ]);

            return true;
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $isAuthError = $this->isAuthenticationError($errorMessage);

            Log::error('Failed to send confirmation email', [
                'lead_id' => $lead->id,
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
                    'failed_smtp_profile_id' => $smtpProfile->id,
                    'smtp_host' => $smtpProfile->host,
                    'smtp_username' => $smtpProfile->username,
                ]);

                // Record authentication failure for monitoring
                $this->recordAuthenticationFailure($smtpProfile);

                // Try to send with an alternative active SMTP profile
                $alternativeSent = $this->sendWithAlternativeProfile($lead, $smtpProfile, $subject, $bodyHtml, $bodyText, $variables);

                if ($alternativeSent) {
                    return true;
                }

                // If no alternative worked, log that the SMTP profile should be checked
                Log::error('All SMTP profiles failed for lead', [
                    'lead_id' => $lead->id,
                    'original_smtp_profile_id' => $smtpProfile->id,
                ]);
            }

            return false;
        }
    }

    /**
     * Prepare template variables from lead data.
     *
     * @param  array<string, mixed>  $additionalVariables
     * @return array<string, string>
     */
    protected function prepareTemplateVariables(Lead $lead, string $confirmationUrl, array $additionalVariables = []): array
    {
        $data = $lead->data ?? [];
        $name = $data['name'] ?? $data['first_name'] ?? $data['last_name'] ?? 'Cher client';
        $email = $lead->email ?? '';

        return array_merge([
            'name' => $name,
            'email' => $email,
            'confirmation_link' => $confirmationUrl,
        ], $additionalVariables);
    }

    /**
     * Render template with variables.
     */
    protected function renderTemplate(string $template, array $variables): string
    {
        $content = $template;

        foreach ($variables as $key => $value) {
            // Support both {{variable}} and {variable} syntax
            $content = str_replace(['{{'.$key.'}}', '{'.$key.'}'], $value, $content);
        }

        return $content;
    }

    /**
     * Configure mailer with SMTP profile settings.
     *
     * @return \Illuminate\Mail\Mailer|\Illuminate\Support\Testing\Fakes\MailFake
     */
    protected function configureMailer(SmtpProfile $smtpProfile, ?string $password = null)
    {
        // Use provided password or get from model (which will decrypt it)
        $decryptedPassword = $password ?? $smtpProfile->password;

        $config = [
            'transport' => 'smtp',
            'host' => $smtpProfile->host,
            'port' => $smtpProfile->port,
            'encryption' => $smtpProfile->encryption === 'none' ? null : $smtpProfile->encryption,
            'username' => $smtpProfile->username,
            'password' => $decryptedPassword,
            'timeout' => null,
        ];

        // Create a temporary mailer configuration
        Config::set('mail.mailers.dynamic_smtp', $config);

        return Mail::mailer('dynamic_smtp');
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
        SmtpProfile $failedProfile,
        string $subject,
        string $bodyHtml,
        string $bodyText,
        array $variables
    ): bool {
        // Get all active SMTP profiles except the failed one
        $alternativeProfiles = SmtpProfile::where('is_active', true)
            ->where('id', '!=', $failedProfile->id)
            ->get();

        if ($alternativeProfiles->isEmpty()) {
            Log::warning('No alternative SMTP profiles available', [
                'lead_id' => $lead->id,
                'failed_smtp_profile_id' => $failedProfile->id,
            ]);

            return false;
        }

        // Try each alternative profile
        foreach ($alternativeProfiles as $alternativeProfile) {
            $password = $alternativeProfile->password;

            if (empty($password)) {
                Log::warning('Alternative SMTP profile has no password', [
                    'lead_id' => $lead->id,
                    'smtp_profile_id' => $alternativeProfile->id,
                ]);

                continue;
            }

            try {
                $mailer = $this->configureMailer($alternativeProfile, $password);

                $mailer->raw($bodyHtml, function ($message) use ($lead, $alternativeProfile, $subject, $bodyText) {
                    $message->to($lead->email)
                        ->subject($subject)
                        ->from($alternativeProfile->from_address, $alternativeProfile->from_name);

                    if (! empty($bodyText)) {
                        $message->text($bodyText);
                    }
                });

                Log::info('Confirmation email sent successfully with alternative SMTP profile', [
                    'lead_id' => $lead->id,
                    'email' => $lead->email,
                    'original_smtp_profile_id' => $failedProfile->id,
                    'alternative_smtp_profile_id' => $alternativeProfile->id,
                    'smtp_host' => $alternativeProfile->host,
                ]);

                return true;
            } catch (\Exception $e) {
                Log::warning('Alternative SMTP profile also failed', [
                    'lead_id' => $lead->id,
                    'smtp_profile_id' => $alternativeProfile->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }

        return false;
    }

    /**
     * Record an authentication failure for monitoring purposes.
     * This helps identify problematic SMTP profiles that may need attention.
     */
    protected function recordAuthenticationFailure(SmtpProfile $smtpProfile): void
    {
        // Log a warning that this SMTP profile is experiencing authentication issues
        // This can be used to identify profiles that need manual review or disabling
        Log::warning('SMTP profile authentication failure recorded', [
            'smtp_profile_id' => $smtpProfile->id,
            'smtp_profile_name' => $smtpProfile->name,
            'smtp_host' => $smtpProfile->host,
            'smtp_username' => $smtpProfile->username,
            'is_active' => $smtpProfile->is_active,
            'recommendation' => 'Review SMTP credentials or consider disabling this profile if issues persist',
        ]);
    }
}
