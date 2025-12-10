<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    /**
     * Authenticate user and return API token for mobile.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('Les identifiants fournis sont incorrects.')],
            ]);
        }

        // Vérifier si le compte est actif
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => [__('Votre compte a été désactivé. Veuillez contacter un administrateur.')],
            ]);
        }

        // Vérifier si l'email est vérifié (si requis)
        if ($user->email_verified_at === null) {
            throw ValidationException::withMessages([
                'email' => [__('Votre adresse email n\'a pas été vérifiée.')],
            ]);
        }

        // Créer ou récupérer un token API pour cet appareil
        $deviceName = $request->device_name ?? 'Mobile Device';
        $tokenName = "Mobile - {$deviceName} - ".now()->format('Y-m-d H:i:s');

        // Générer un nouveau token
        $token = ApiToken::generate();

        $apiToken = ApiToken::create([
            'user_id' => $user->id,
            'name' => $tokenName,
            'token' => $token,
            'expires_at' => null, // Pas d'expiration par défaut pour mobile
        ]);

        return response()->json([
            'message' => __('Authentification réussie'),
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->name ?? null,
                ],
                'token' => $token,
                'token_id' => $apiToken->id,
            ],
        ]);
    }

    /**
     * Logout user and revoke token.
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');

        if ($token) {
            $apiToken = ApiToken::where('token', $token)->first();
            if ($apiToken) {
                $apiToken->delete();
            }
        }

        return response()->json([
            'message' => __('Déconnexion réussie'),
        ]);
    }

    /**
     * Get authenticated user information.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => __('Non authentifié'),
            ], 401);
        }

        $user->loadMissing('role', 'callCenter');

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name ?? null,
                'call_center' => $user->callCenter?->name ?? null,
            ],
        ]);
    }
}
