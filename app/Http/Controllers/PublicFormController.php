<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\FormValidationService;
use App\Services\LeadConfirmationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicFormController extends Controller
{
    public function __construct(
        protected FormValidationService $formValidationService,
        protected LeadConfirmationService $leadConfirmationService
    ) {}

    /**
     * Submit a form and create a lead.
     */
    public function submit(Request $request, Form $form)
    {
        // Log the submission attempt for security audit
        \Log::info('Form submission attempt', [
            'form_id' => $form->id,
            'form_uid' => $form->uid,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Check if form is active
        if (! $form->is_active) {
            \Log::warning('Attempt to submit inactive form', [
                'form_id' => $form->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Ce formulaire n\'est pas actif.',
            ], 403);
        }

        // Check if form has SMTP profile and email template
        if (! $form->smtp_profile_id || ! $form->email_template_id) {
            return response()->json([
                'message' => 'Ce formulaire n\'est pas configuré correctement.',
            ], 400);
        }

        // Validate form data
        try {
            $validatedData = $this->formValidationService->validate($form, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Form submission validation failed', [
                'form_id' => $form->id,
                'ip' => $request->ip(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'message' => 'Les données soumises sont invalides.',
                'errors' => $e->errors(),
            ], 422);
        }

        // Extract email from data
        $email = $validatedData['email'] ?? null;
        if (! $email) {
            // Try to find email in other common fields
            $email = $validatedData['email_address'] ?? $validatedData['e_mail'] ?? null;
        }

        if (! $email) {
            return response()->json([
                'message' => 'Une adresse email est requise.',
            ], 422);
        }

        // Create lead with call center association
        $lead = $form->leads()->create([
            'data' => $validatedData,
            'email' => $email,
            'status' => 'pending_email',
            'email_confirmation_token' => Str::random(64),
            'email_confirmation_token_expires_at' => now()->addHours(24),
            'call_center_id' => $form->call_center_id,
        ]);

        // Send confirmation email
        $emailSent = $this->leadConfirmationService->sendConfirmationEmail($lead);

        if (! $emailSent) {
            \Log::warning('Failed to send confirmation email for lead', ['lead_id' => $lead->id]);
        }

        // Log successful submission
        \Log::info('Form submitted successfully', [
            'form_id' => $form->id,
            'lead_id' => $lead->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Votre formulaire a été soumis avec succès. Veuillez vérifier votre email pour confirmer votre inscription.',
            'lead_id' => $lead->id,
        ], 201);
    }
}
