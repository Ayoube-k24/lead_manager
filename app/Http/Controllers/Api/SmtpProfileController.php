<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmtpProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmtpProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $smtpProfiles = SmtpProfile::latest()->get();

        return response()->json([
            'data' => $smtpProfiles,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'string', 'in:tls,ssl,none'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'from_address' => ['required', 'string', 'email', 'max:255'],
            'from_name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $smtpProfile = SmtpProfile::create($validator->validated());

        return response()->json([
            'message' => 'Profil SMTP créé avec succès',
            'data' => $smtpProfile,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SmtpProfile $smtpProfile): JsonResponse
    {
        return response()->json([
            'data' => $smtpProfile,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SmtpProfile $smtpProfile): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'host' => ['sometimes', 'required', 'string', 'max:255'],
            'port' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['sometimes', 'required', 'string', 'in:tls,ssl,none'],
            'username' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string'],
            'from_address' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'from_name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Only update password if provided
        $data = $validator->validated();
        if (! isset($data['password'])) {
            unset($data['password']);
        }

        $smtpProfile->update($data);

        return response()->json([
            'message' => 'Profil SMTP mis à jour avec succès',
            'data' => $smtpProfile,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SmtpProfile $smtpProfile): JsonResponse
    {
        // Check if profile is used by forms
        if ($smtpProfile->forms()->exists()) {
            return response()->json([
                'message' => 'Ce profil SMTP est utilisé par des formulaires et ne peut pas être supprimé',
            ], 422);
        }

        $smtpProfile->delete();

        return response()->json([
            'message' => 'Profil SMTP supprimé avec succès',
        ]);
    }
}
