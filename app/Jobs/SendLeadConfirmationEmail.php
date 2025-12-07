<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendLeadConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Lead $lead
    ) {}

    /**
     * Execute the job.
     */
    public function handle(LeadConfirmationService $confirmationService): void
    {
        // Vérifier que le lead existe toujours
        if (! $this->lead->exists) {
            Log::warning('Lead does not exist anymore', [
                'lead_id' => $this->lead->id,
            ]);

            return;
        }

        // Vérifier que le formulaire et le profil SMTP sont toujours valides
        $form = $this->lead->form;
        if (! $form || ! $form->smtpProfile || ! $form->emailTemplate) {
            Log::warning('Form or SMTP profile missing for lead', [
                'lead_id' => $this->lead->id,
                'form_id' => $form?->id,
            ]);

            return;
        }

        if (! $form->smtpProfile->is_active) {
            Log::warning('SMTP profile is not active', [
                'lead_id' => $this->lead->id,
                'smtp_profile_id' => $form->smtpProfile->id,
            ]);

            return;
        }

        // Envoyer l'email de confirmation
        // This job is used as a fallback when immediate sending fails
        $emailSent = $confirmationService->sendConfirmationEmail($this->lead);

        if (! $emailSent) {
            Log::error('Failed to send confirmation email in job', [
                'lead_id' => $this->lead->id,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
            ]);

            // Relancer une exception pour que Laravel réessaie le job
            // Only retry if we haven't exceeded max attempts
            if ($this->attempts() < $this->tries) {
                throw new \Exception('Failed to send confirmation email');
            }

            // If max attempts reached, log and stop
            Log::error('Confirmation email job failed after all retries', [
                'lead_id' => $this->lead->id,
                'total_attempts' => $this->attempts(),
            ]);

            return;
        }

        Log::info('Confirmation email sent successfully via queue job', [
            'lead_id' => $this->lead->id,
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?\Throwable $exception): void
    {
        Log::error('SendLeadConfirmationEmail job failed after all retries', [
            'lead_id' => $this->lead->id,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
