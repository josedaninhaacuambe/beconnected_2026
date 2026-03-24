<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MozambiqueLocationSeeder::class,
            StoreCategorySeeder::class,
            VisibilityPlanSeeder::class,
            DemoDataSeeder::class,
        ]);

        // Admin padrão
        User::create([
            'name' => 'Administrador Beconnect',
            'email' => 'admin@beconnect.co.mz',
            'phone' => '258840000000',
            'password' => bcrypt('Beconnect@2025'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }
}
