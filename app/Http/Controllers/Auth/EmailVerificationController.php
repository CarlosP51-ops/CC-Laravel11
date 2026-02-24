<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use App\Http\Resources\EmailVerificationResponse; 

class EmailVerificationController extends Controller
{
    /**
     * Envoyer un nouveau lien de vérification
     */
    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return new EmailVerificationResponse([
                'status' => 'error',
                'message' => 'Email déjà vérifié.'
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return new EmailVerificationResponse([
            'status' => 'success',
            'message' => 'Lien de vérification envoyé.'
        ]);
    }

    /**
     * Vérifier l'email
     */
    public function verify(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return new EmailVerificationResponse([
                'status' => 'error',
                'message' => 'Email déjà vérifié.'
            ]);
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return new EmailVerificationResponse([
            'status' => 'success',
            'message' => 'Email vérifié avec succès.'
        ]);
    }
}