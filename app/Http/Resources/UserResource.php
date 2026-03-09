<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        $userData = [
            'id' => $this->id,
            'name' => $this->fullname,
            'fullname' => $this->fullname,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value ?? $this->role, // Extraire la valeur de l'enum
        ];

        // Vérifiez si l'utilisateur est un vendeur
        if ($this->role->value === 'vendor' || $this->role === 'vendor') {
            // Chargez également les détails du vendeur associés
            $seller = $this->seller; // Ceci suppose que vous avez une relation définie dans le modèle User

            $userData['store_name'] = $seller->store_name;
            $userData['slug'] = $seller->slug;
            $userData['description'] = $seller->description;
            $userData['logo'] = $seller->logo;
            $userData['banner'] = $seller->banner;
            $userData['address'] = $seller->address;
            $userData['city'] = $seller->city;
            $userData['postal_code'] = $seller->postal_code;
            $userData['country'] = $seller->country;
            $userData['is_verified'] = $seller->is_verified;
            $userData['is_active'] = $seller->is_active;
        }

        return $userData;
    }
}