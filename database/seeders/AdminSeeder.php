<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@csstore.com'],
            [
                'fullname'  => 'Administrateur',
                'password'  => Hash::make('Root@2003!'),
                'role'      => 'admin',
                'is_active' => true,
                'phone'     => '+00000000000',
            ]
        );

        $this->command->info('✅ Admin créé : admin@csstore.com / Admin@2024!');
    }
}
