<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\LeadConfirmationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendLeadReminderEmail implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

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
        // Vérifier que le lead est toujours en attente
        if (! in_array($this->lead->status, ['pending_email', 'email_confirmed'])) {
            return;
        }

        // Vérifier que le token n'a pas expiré ou régénérer un nouveau token
        if (! $this->lead->isConfirmationTokenValid()) {
            $this->lead->email_confirmation_token = \Illuminate\Support\Str::random(64);
            $this->lead->email_confirmation_token_expires_at = now()->addHours(24);
            $this->lead->save();
        }

        // Envoyer l'email de relance
        $confirmationService->sendConfirmationEmail($this->lead);
    }
}
