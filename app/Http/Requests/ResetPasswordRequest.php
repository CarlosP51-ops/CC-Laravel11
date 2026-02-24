<?php
// app/Http/Requests/ResetPasswordRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Autorise toutes les requêtes
    }

    public function rules(): array
    {
        return [
            'token' => 'required', // Utilisé directement ici
            'email' => 'required|string|email|exists:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Un jeton est requis pour la réinitialisation du mot de passe.',
            'email.required' => 'Une adresse e-mail est requise.',
            'email.email' => 'Veuillez fournir une adresse e-mail valide.',
            'email.exists' => 'Cette adresse e-mail n\'est pas enregistrée.',
            'password.required' => 'Veuillez entrer un mot de passe.',
            'password.confirmed' => 'Les mots de passe ne correspondent pas.',
        ];
    }
}