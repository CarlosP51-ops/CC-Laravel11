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
       $timestamp = time() * rand(0, 99);
        return [
            'fullname' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail.'.'. $timestamp,
            'password' => bcrypt($this->faker->password),
            'phone' => $this->faker->phoneNumber,
            'role' => $this->faker->randomElement(['client', 'vendor']), 
        ];
    }

    public function vendor()
    {
        // Générer les détails du magasin
        $store_name = $this->faker->company;
        $slug = Str::slug($store_name);
        $timestamp = time() * rand(0, 1000000);

        // Créer l'utilisateur avec le rôle 'vendor'
        $user = $this->create([
             'fullname' => $this->faker->name,
           'email' => $this->faker->unique()->safeEmail.'.'. $timestamp+rand(0,100000),
            'password' => bcrypt($this->faker->password),
            'phone' => $this->faker->phoneNumber, 
            'role' => 'vendor',
      ]);

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

        return $user; 
    }
}