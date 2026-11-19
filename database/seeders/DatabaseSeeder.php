<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@jamumakyuli.com'],
            [
                'name' => 'Admin',
                'role' => UserRole::ADMIN,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        $this->call([
            SettingSeeder::class,
            PageSeeder::class,
            CatalogSeeder::class,
            SlideSeeder::class,
        ]);
    }
}
