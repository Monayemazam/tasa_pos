<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialOwnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ownerRole = Role::where('name', 'Owner')->first();

        // Check if an Owner already exists
        $hasOwner = User::role('Owner')->exists();

        if (! $hasOwner) {
            $email = env('INITIAL_OWNER_EMAIL', 'owner@tasa.com');
            $name = env('INITIAL_OWNER_NAME', 'Tasa Owner');
            $password = env('INITIAL_OWNER_PASSWORD', 'Tasa@Owner2026!');

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'phone' => '01700000000',
                    'email_verified_at' => now(),
                    'is_active' => true,
                ]
            );

            $user->assignSingleRole('Owner');
        }
    }
}
