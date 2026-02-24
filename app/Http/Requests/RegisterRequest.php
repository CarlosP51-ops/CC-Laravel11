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
        $rules = [
            'name' => self::NAME_RULES,
            'email' => self::EMAIL_RULES,
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => self::PHONE_RULES,
            'role' => 'required|string|in:client,vendor',
        ];

        if ($this->role === 'vendor') {
            // Ajouter les règles spécifiques au vendeur
            $rules = array_merge($rules, [
                'store_name' => self::STORE_NAME_RULES,
                'slug' => self::SLUG_RULES,
                'description' => self::DESCRIPTION_RULES,
                'logo' => 'nullable|image', // Règle pour l'image
                'banner' => 'nullable|image',
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
            'name.required' => 'Please provide your name.',
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'Please enter a password.',
            'password.confirmed' => 'Passwords do not match.',
            'phone.max' => 'The phone number may not be greater than 20 characters.',
            'role.required' => 'Please specify a role (client or vendor).',
            'store_name.required' => 'Please provide your store name.',
            'slug.required' => 'Please provide a unique store slug.',
            'slug.unique' => 'This slug is already taken.',
            'description' => 'The description is optional.',
            'logo.image' => 'The logo must be an image file.',
            'banner.image' => 'The banner must be an image file.',
            'address.max' => 'The address may not be greater than 255 characters.',
            'city.max' => 'The city may not be greater than 100 characters.',
            'postal_code.max' => 'The postal code may not be greater than 20 characters.',
            'country.max' => 'The country may not be greater than 100 characters.',
        ];
    }
}