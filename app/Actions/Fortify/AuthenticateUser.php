<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    /**
     * Authenticate the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return mixed
     */
    public function __invoke($request)
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('Les identifiants fournis sont incorrects.')],
            ]);
        }

        // Vérifier si le compte est actif
        if (! $user->is_active) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('Votre compte a été désactivé. Veuillez contacter un administrateur.')],
            ]);
        }

        // Vérifier si l'email est vérifié (si requis)
        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                Fortify::username() => [__('Votre adresse email n\'a pas été vérifiée.')],
            ]);
        }

        return $user;
    }
}






