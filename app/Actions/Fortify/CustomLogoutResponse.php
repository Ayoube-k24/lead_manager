<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Session;
use Laravel\Fortify\Contracts\LogoutResponse;

class CustomLogoutResponse implements LogoutResponse
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Regenerate session and CSRF token after logout to prevent 419 errors
        // Use regenerate(true) to force regeneration even if session was invalidated
        Session::regenerate(true);
        Session::regenerateToken();

        // Redirect to home page after logout
        return redirect()->route('home');
    }
}
