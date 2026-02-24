<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    // Constantes pour les règles de validation
    private const EMAIL_RULES = 'required|string|email|exists:users,email';

    public function authorize(): bool
    {
        return true; // Autorise toutes les requêtes
    }

    public function rules(): array
    {
        return [
            'email' => self::EMAIL_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Une adresse e-mail est requise.',
            'email.email' => 'Veuillez fournir une adresse e-mail valide.',
            'email.exists' => 'Cette adresse e-mail n\'est pas enregistrée.',
        ];
    }
}