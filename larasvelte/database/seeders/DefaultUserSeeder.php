<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * After change run php artisan db:seed --class=DefaultUserSeeder
     *
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Default Test User',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
    }
}
