<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Seller; 
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'fullname' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => bcrypt($this->faker->password),
            'phone' => $this->faker->phoneNumber,
            'role' => 'client'
        ];
    }

    public function vendor()
    {
        return $this->state([
            'role' => 'vendor',
        ])->afterCreating(function (User $user) {
            // Générer les détails du magasin
            $store_name = $this->faker->company;
            $slug = Str::slug($store_name);

            Seller::create([
                'user_id' => $user->id, 
                'store_name' => $store_name,
                'slug' => $slug,
                'description' => $this->faker->text(100),
                'address' => $this->faker->address,
                'city' => $this->faker->city,
                'postal_code' => $this->faker->postcode,
                'country' => $this->faker->country,
            ]);
        });
    }
}