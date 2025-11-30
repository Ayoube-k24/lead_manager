<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Si l'utilisateur est connecté mais son compte est désactivé, le déconnecter
        if ($user && ! $user->is_active) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => __('Votre compte a été désactivé. Veuillez contacter un administrateur.'),
                ], 403);
            }

            return redirect()->route('login')
                ->with('error', __('Votre compte a été désactivé. Veuillez contacter un administrateur.'));
        }

        return $next($request);
    }
}

