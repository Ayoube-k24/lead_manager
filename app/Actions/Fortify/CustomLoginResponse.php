<?php

namespace App\Actions\Fortify;

use Laravel\Fortify\Contracts\LoginResponse;

class CustomLoginResponse implements LoginResponse
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('dashboard');
        }

        // Load role relationship to avoid N+1 queries
        $user->loadMissing('role');

        // Handle JSON requests (API)
        if ($request->wantsJson()) {
            return response()->json([
                'two_factor' => false,
                'user' => $user,
                'redirect' => $this->getRedirectUrl($user),
            ]);
        }

        // Redirect based on user role
        return redirect()->to($this->getRedirectUrl($user));
    }

    /**
     * Get the redirect URL based on user role.
     *
     * @param  \App\Models\User|null  $user
     */
    private function getRedirectUrl($user): string
    {
        if (! $user || ! $user->role) {
            return route('dashboard');
        }

        return match ($user->role->slug) {
            'super_admin' => route('dashboard.admin'),
            'call_center_owner' => route('dashboard.owner'),
            'agent' => route('dashboard.agent'),
            default => route('dashboard'),
        };
    }
}
