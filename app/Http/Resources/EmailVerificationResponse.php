<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailVerificationResponse extends JsonResource
{
    /**
     * Transforme la ressource en un tableau.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => $this->status === 'success',
            'message' => __($this->message),
        ];
    }
}