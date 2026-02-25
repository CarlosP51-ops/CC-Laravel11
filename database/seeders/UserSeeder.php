<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    public function run()
    {
        // // Créer un utilisateur admin
        User::factory()->create([
            'fullname' => 'Admin User', 
            'email' => 'admin@example.com', 
            'password' => bcrypt('password1123'),
            'role' => 'admin',
        ]);

        // Créer des utilisateurs normaux
      User::factory()->count(10)->create();

        // Créer des vendeurs
        User::factory()->count(5)->vendor()->create();
    }
}