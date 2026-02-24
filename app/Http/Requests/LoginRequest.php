<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    // Constants for validation rules
    private const EMAIL_RULES = 'required|string|email';
    private const PASSWORD_RULES = 'required|string';

    public function authorize(): bool
    {
        return true; // Autorise toutes les requêtes
    }

    public function rules(): array
    {
        return [
            'email' => self::EMAIL_RULES,
            'password' => self::PASSWORD_RULES,
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Une adresse e-mail est requise.',
            'email.email' => 'Veuillez fournir une adresse e-mail valide.',
            'password.required' => 'Veuillez saisir un mot de passe.',
        ];
    }
}