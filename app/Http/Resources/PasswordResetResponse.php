<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PasswordResetResponse extends JsonResource
{
    public function __construct($data)
    {
        parent::__construct($data); // Passez le tableau au constructeur parent pour qu'il soit accessible.
    }

    public function toArray($request)
    {
        return [
            'status' => $this->status, // Assurez-vous que vous accédez aux propriétés correctement.
            'message' => $this->message,
        ];
    }
}