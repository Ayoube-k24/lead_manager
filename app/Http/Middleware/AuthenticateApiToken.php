<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');

        if (! $token) {
            return response()->json([
                'message' => 'Token d\'authentification manquant',
            ], 401);
        }

        $apiToken = ApiToken::where('token', $token)->first();

        if (! $apiToken || ! $apiToken->isValid()) {
            return response()->json([
                'message' => 'Token invalide ou expiré',
            ], 401);
        }

        // Update last used timestamp
        $apiToken->update(['last_used_at' => now()]);

        // Set authenticated user - ensure user is loaded
        $user = $apiToken->user;
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Also set it on Auth facade for compatibility
        auth()->setUser($user);

        return $next($request);
    }
}
