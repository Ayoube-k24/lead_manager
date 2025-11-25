<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $templates = EmailTemplate::latest()->get();

        return response()->json([
            'data' => $templates,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $template = EmailTemplate::create($validator->validated());

        return response()->json([
            'message' => 'Template d\'email créé avec succès',
            'data' => $template,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        return response()->json([
            'data' => $emailTemplate,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'subject' => ['sometimes', 'required', 'string', 'max:255'],
            'body_html' => ['sometimes', 'required', 'string'],
            'body_text' => ['nullable', 'string'],
            'variables' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $emailTemplate->update($validator->validated());

        return response()->json([
            'message' => 'Template d\'email mis à jour avec succès',
            'data' => $emailTemplate,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        // Check if template is used by forms
        if ($emailTemplate->forms()->exists()) {
            return response()->json([
                'message' => 'Ce template est utilisé par des formulaires et ne peut pas être supprimé',
            ], 422);
        }

        $emailTemplate->delete();

        return response()->json([
            'message' => 'Template d\'email supprimé avec succès',
        ]);
    }
}
