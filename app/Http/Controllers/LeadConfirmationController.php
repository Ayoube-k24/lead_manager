<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\LeadDistributionService;
use Illuminate\Http\Request;

class LeadConfirmationController extends Controller
{
    public function __construct(
        protected LeadDistributionService $distributionService
    ) {}

    /**
     * Confirm email using token.
     */
    public function confirm(Request $request, string $token)
    {
        $lead = Lead::with(['form', 'callCenter'])->where('email_confirmation_token', $token)->first();

        if (! $lead) {
            return view('leads.confirmation-error', [
                'message' => 'Lien de confirmation invalide ou expiré.',
            ]);
        }

        if (! $lead->isConfirmationTokenValid()) {
            return view('leads.confirmation-error', [
                'message' => 'Ce lien de confirmation a expiré. Veuillez contacter le support.',
            ]);
        }

        if ($lead->isEmailConfirmed()) {
            return view('leads.confirmation-success', [
                'message' => 'Votre email a déjà été confirmé.',
            ]);
        }

        // Ensure call_center_id is set before confirming email
        // The Observer will automatically handle distribution when status changes to email_confirmed
        if (! $lead->call_center_id && $lead->form && $lead->form->call_center_id) {
            $lead->call_center_id = $lead->form->call_center_id;
            $lead->saveQuietly(); // Save without triggering observer
        }

        // Confirm the email - Observer will automatically distribute
        $lead->confirmEmail();

        // Reload to get the latest state (Observer may have assigned the lead)
        $lead->refresh();

        return view('leads.confirmation-success', [
            'message' => 'Votre email a été confirmé avec succès. Un agent vous contactera prochainement.',
        ]);
    }
}
