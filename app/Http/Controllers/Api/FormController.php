<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Form;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Form::with(['smtpProfile', 'emailTemplate', 'callCenter']);

        // Super admin can see all, others see only their call center's forms
        if ($user->role?->slug !== 'super_admin' && $user->call_center_id) {
            $query->where('call_center_id', $user->call_center_id);
        }

        $forms = $query->latest()->get();

        return response()->json([
            'data' => $forms,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date', 'datetime', 'url', 'multiselect', 'radiolist', 'checkboxlist', 'consent'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.tag' => ['nullable', 'string', 'max:255'],
            'fields.*.visibility' => ['nullable', 'string', Rule::in(['visible', 'hidden'])],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'fields.*.content_regex' => ['nullable', 'string', 'max:500'],
            'fields.*.validation_rules' => ['nullable', 'array'],
            'fields.*.validation_rules.min' => ['nullable', 'numeric', 'min:0'],
            'fields.*.validation_rules.max' => ['nullable', 'numeric', 'min:0'],
            'fields.*.validation_rules.min_length' => ['nullable', 'integer', 'min:0'],
            'fields.*.validation_rules.max_length' => ['nullable', 'integer', 'min:0'],
            'fields.*.validation_rules.regex' => ['nullable', 'string'],
            'fields.*.validation_rules.in' => ['nullable'],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.type,select', 'required_if:fields.*.type,multiselect', 'required_if:fields.*.type,radiolist', 'required_if:fields.*.type,checkboxlist'],
            'smtp_profile_id' => ['nullable', 'exists:smtp_profiles,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'call_center_id' => ['required', 'exists:call_centers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();

        // Check authorization
        if ($user->role?->slug !== 'super_admin') {
            if ($user->call_center_id !== $request->call_center_id) {
                return response()->json([
                    'message' => 'Vous n\'avez pas l\'autorisation de créer un formulaire pour ce centre d\'appels',
                ], 403);
            }
        }

        $form = Form::create($validator->validated());

        return response()->json([
            'message' => 'Formulaire créé avec succès',
            'data' => $form->load(['smtpProfile', 'emailTemplate', 'callCenter']),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Form $form): JsonResponse
    {
        $user = $request->user();

        // Check authorization
        if ($user->role?->slug !== 'super_admin') {
            if ($form->call_center_id !== $user->call_center_id) {
                return response()->json([
                    'message' => 'Formulaire non trouvé',
                ], 404);
            }
        }

        return response()->json([
            'data' => $form->load(['smtpProfile', 'emailTemplate', 'callCenter']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Form $form): JsonResponse
    {
        $user = $request->user();

        // Check authorization
        if ($user->role?->slug !== 'super_admin') {
            if ($form->call_center_id !== $user->call_center_id) {
                return response()->json([
                    'message' => 'Formulaire non trouvé',
                ], 404);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'fields' => ['sometimes', 'required', 'array', 'min:1'],
            'fields.*.name' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', Rule::in(['text', 'email', 'tel', 'textarea', 'select', 'checkbox', 'file', 'number', 'date', 'datetime', 'url', 'multiselect', 'radiolist', 'checkboxlist', 'consent'])],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['sometimes', 'boolean'],
            'fields.*.tag' => ['nullable', 'string', 'max:255'],
            'fields.*.visibility' => ['nullable', 'string', Rule::in(['visible', 'hidden'])],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:1'],
            'fields.*.help_text' => ['nullable', 'string', 'max:500'],
            'fields.*.default_value' => ['nullable'],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'fields.*.content_regex' => ['nullable', 'string', 'max:500'],
            'fields.*.validation_rules' => ['nullable', 'array'],
            'fields.*.validation_rules.min' => ['nullable', 'numeric', 'min:0'],
            'fields.*.validation_rules.max' => ['nullable', 'numeric', 'min:0'],
            'fields.*.validation_rules.min_length' => ['nullable', 'integer', 'min:0'],
            'fields.*.validation_rules.max_length' => ['nullable', 'integer', 'min:0'],
            'fields.*.validation_rules.regex' => ['nullable', 'string'],
            'fields.*.validation_rules.in' => ['nullable'],
            'fields.*.options' => ['nullable', 'array', 'required_if:fields.*.type,select', 'required_if:fields.*.type,multiselect', 'required_if:fields.*.type,radiolist', 'required_if:fields.*.type,checkboxlist'],
            'smtp_profile_id' => ['nullable', 'exists:smtp_profiles,id'],
            'email_template_id' => ['nullable', 'exists:email_templates,id'],
            'call_center_id' => ['sometimes', 'required', 'exists:call_centers,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check call center authorization if changing
        if ($request->has('call_center_id') && $request->call_center_id !== $form->call_center_id) {
            if ($user->role?->slug !== 'super_admin') {
                if ($request->call_center_id !== $user->call_center_id) {
                    return response()->json([
                        'message' => 'Vous n\'avez pas l\'autorisation de modifier le centre d\'appels',
                    ], 403);
                }
            }
        }

        $form->update($validator->validated());

        return response()->json([
            'message' => 'Formulaire mis à jour avec succès',
            'data' => $form->load(['smtpProfile', 'emailTemplate', 'callCenter']),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Form $form): JsonResponse
    {
        $user = $request->user();

        // Check authorization
        if ($user->role?->slug !== 'super_admin') {
            if ($form->call_center_id !== $user->call_center_id) {
                return response()->json([
                    'message' => 'Formulaire non trouvé',
                ], 404);
            }
        }

        $form->delete();

        return response()->json([
            'message' => 'Formulaire supprimé avec succès',
        ]);
    }
}
