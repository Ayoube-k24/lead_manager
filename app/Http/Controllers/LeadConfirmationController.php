<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadConfirmationController extends Controller
{
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

        return view('leads.confirmation-success', [
            'message' => 'Votre email a été confirmé avec succès. Un agent vous contactera prochainement.',
        ]);
    }
}
