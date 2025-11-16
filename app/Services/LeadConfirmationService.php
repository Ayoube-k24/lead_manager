<?php

namespace App\Services;

use App\Models\Form;
use App\Models\Lead;
use App\Models\SmtpProfile;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Config;
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
            $lead->email_confirmation_token_expires_at = now()->addHours(24);
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

        // Configure mailer with SMTP profile
        $mailer = $this->configureMailer($smtpProfile);

        try {
            $mailer->raw($bodyText, function ($message) use ($lead, $smtpProfile, $subject, $bodyHtml) {
                $message->to($lead->email)
                    ->subject($subject)
                    ->from($smtpProfile->from_address, $smtpProfile->from_name)
                    ->html($bodyHtml);
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to send confirmation email', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);

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
     */
    protected function configureMailer(SmtpProfile $smtpProfile): Mailer
    {
        $config = [
            'transport' => 'smtp',
            'host' => $smtpProfile->host,
            'port' => $smtpProfile->port,
            'encryption' => $smtpProfile->encryption === 'none' ? null : $smtpProfile->encryption,
            'username' => $smtpProfile->username,
            'password' => $smtpProfile->password,
            'timeout' => null,
        ];

        // Create a temporary mailer configuration
        Config::set('mail.mailers.dynamic_smtp', $config);

        return Mail::mailer('dynamic_smtp');
    }
}
