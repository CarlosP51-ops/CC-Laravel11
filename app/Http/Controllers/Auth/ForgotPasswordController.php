<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Http\Resources\PasswordResetResponse;
use App\Notifications\CustomResetPasswordNotification;

class ForgotPasswordController extends Controller
{
    /**
     * Envoyer le lien de réinitialisation
     */
   public function forgot(ForgotPasswordRequest $request)
{
   
    // Validation et génération du token
    $status = Password::sendResetLink($request->only('email'), function ($user, $token) {
        $user->notify(new CustomResetPasswordNotification($token));
    });

    return response()->json([
        'status' => $status === Password::RESET_LINK_SENT ? 'success' : 'error',
        'message' => $status
    ]);
}
    /**
     * Réinitialiser le mot de passe
     */
    public function reset(Request $request)
    {
        // Valider les données entrantes
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|confirmed|min:8',
            'token' => 'required',
        ]);

        // Réinitialiser le mot de passe
        $status = Password::reset($request->only('email', 'password', 'password_confirmation', 'token'), function ($user, $password) {
            $user->forceFill([
                'password' => bcrypt($password),
            ])->save();
        });

        // Retourner une réponse JSON
        return $status === Password::PASSWORD_RESET
            ? response()->json(['status' => 'Votre mot de passe a été réinitialisé avec succès !'], 200)
            : response()->json(['status' => 'Le lien de réinitialisation est invalide.'], 422);
    }

}