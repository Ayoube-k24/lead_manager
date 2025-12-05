<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ApiTokenController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = ApiToken::where('user_id', $request->user()->id)
            ->latest()
            ->get()
            ->map(function ($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'expires_at' => $token->expires_at?->toIso8601String(),
                    'created_at' => $token->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $tokens,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $token = ApiToken::generate();
        $expiresAt = $validator->validated()['expires_at'] ?? null;

        $apiToken = ApiToken::create([
            'user_id' => $request->user()->id,
            'name' => $validator->validated()['name'],
            'token' => $token,
            'expires_at' => $expiresAt ? now()->parse($expiresAt) : null,
        ]);

        return response()->json([
            'message' => 'Token API créé avec succès',
            'data' => [
                'id' => $apiToken->id,
                'name' => $apiToken->name,
                'token' => $token, // Only show token once
                'expires_at' => $apiToken->expires_at?->toIso8601String(),
                'created_at' => $apiToken->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, int $apiToken): JsonResponse
    {
        // Find the token manually to avoid model binding issues
        $apiTokenModel = ApiToken::find($apiToken);

        if (! $apiTokenModel) {
            return response()->json([
                'message' => 'Token non trouvé',
            ], 404);
        }

        // Get user from request - try multiple methods for compatibility
        $user = $request->user() ?? auth()->user();

        // Check ownership
        if (! $user || $apiTokenModel->user_id !== $user->id) {
            return response()->json([
                'message' => 'Token non trouvé',
            ], 404);
        }

        $apiTokenModel->delete();

        return response()->json([
            'message' => 'Token supprimé avec succès',
        ]);
    }
}
