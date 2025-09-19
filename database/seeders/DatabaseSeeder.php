<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Ensure core roles exist (idempotent)
        $roles = collect(['admin', 'support', 'user', 'verkoper', 'contactpersoon'])
            ->map(fn ($name) => Role::firstOrCreate(['name' => $name]));

        // 2. Create or update an admin user
        $admin = User::query()->firstOrCreate(
            ['email' => 'jayavandepol@hotmail.com'],
            [
                'name' => 'Administrator',
            ]
        );

        // If the user already existed and you want to guarantee known password on re-run, uncomment:
        // $admin->forceFill(['password' => Hash::make('test')])->save();

        // 3. Assign (or ensure) admin role
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // Optionally generate demo users (commented out)
        // User::factory(10)->create();
    }
}
