<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'default@example.test'],
            [
                'name' => 'Default Test User',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );
    }
}
