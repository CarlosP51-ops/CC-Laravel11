<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    // Constants for validation
    private const NAME_RULES = 'required|string|max:255';
    private const EMAIL_RULES = 'required|string|email|max:255|unique:users';
    private const PHONE_RULES = 'nullable|string|max:20';
    private const STORE_NAME_RULES = 'required|string|max:255';
    private const SLUG_RULES = 'required|string|max:255|unique:sellers';
    private const DESCRIPTION_RULES = 'nullable|string';
    private const ADDRESS_RULES = 'nullable|string|max:255';
    private const CITY_RULES = 'nullable|string|max:100';
    private const POSTAL_CODE_RULES = 'nullable|string|max:20';
    private const COUNTRY_RULES = 'nullable|string|max:100';

    public function authorize(): bool
    {
        return true; // Always authorize for simplicity; adjust as needed.
    }

    public function rules(): array
    {
        // Log pour debug
        \Log::info('RegisterRequest - Role reçu:', ['role' => $this->role]);
        \Log::info('RegisterRequest - Tous les champs:', $this->all());
        
        $rules = [
            'name' => self::NAME_RULES,
            'email' => self::EMAIL_RULES,
            'password' => ['required', Password::defaults()],
            'password_confirmation' => 'required_with:password|same:password',
            'phone' => self::PHONE_RULES,
            'role' => 'required|string|in:client,vendor',
        ];

        if ($this->role === 'vendor') {
            // Ajouter les règles spécifiques au vendeur
            $rules = array_merge($rules, [
                'store_name' => self::STORE_NAME_RULES,
                'slug' => self::SLUG_RULES,
                'description' => self::DESCRIPTION_RULES,
                'logo' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:2048',
                'banner' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
                'address' => self::ADDRESS_RULES,
                'city' => self::CITY_RULES,
                'postal_code' => self::POSTAL_CODE_RULES,
                'country' => self::COUNTRY_RULES,
            ]);
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Veuillez fournir votre nom complet.',
            'email.required' => 'Une adresse email est requise.',
            'email.email' => 'Veuillez fournir une adresse email valide.',
            'email.unique' => 'Cet email est déjà enregistré.',
            'password.required' => 'Veuillez entrer un mot de passe.',
            'password_confirmation.required_with' => 'Veuillez confirmer votre mot de passe.',
            'password_confirmation.same' => 'Les mots de passe ne correspondent pas.',
            'phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères.',
            'role.required' => 'Veuillez spécifier un rôle (client ou vendeur).',
            'role.in' => 'Le rôle doit être client ou vendor.',
            'store_name.required' => 'Veuillez fournir le nom de votre boutique.',
            'slug.required' => 'Veuillez fournir un slug unique pour votre boutique.',
            'slug.unique' => 'Ce slug est déjà utilisé.',
            'logo.image' => 'Le logo doit être une image.',
            'logo.mimes' => 'Le logo doit être au format jpeg, jpg, png, gif ou webp.',
            'logo.max' => 'Le logo ne peut pas dépasser 2 Mo.',
            'banner.image' => 'La bannière doit être une image.',
            'banner.mimes' => 'La bannière doit être au format jpeg, jpg, png, gif ou webp.',
            'banner.max' => 'La bannière ne peut pas dépasser 5 Mo.',
        ];
    }
}