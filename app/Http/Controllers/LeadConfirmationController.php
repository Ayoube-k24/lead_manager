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
        $lead = Lead::where('email_confirmation_token', $token)->first();

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

        // Confirm the email
        $lead->confirmEmail();

        // Try to automatically distribute the lead to an agent
        if (! $lead->assigned_to && $lead->call_center_id) {
            $agent = $this->distributionService->distributeLead($lead);
            if ($agent) {
                $this->distributionService->assignToAgent($lead, $agent);
                $lead->markAsPendingCall();
            }
        }

        return view('leads.confirmation-success', [
            'message' => 'Votre email a été confirmé avec succès. Un agent vous contactera prochainement.',
        ]);
    }
}
