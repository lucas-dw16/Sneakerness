<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * DatabaseSeeder - Hoofdseeder voor volledige database setup.
 * 
 * Runt alle seeders in de juiste volgorde met 5 items per model.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting database seeding...');
        
        // 1. Ensure core roles exist (idempotent)
        $this->command->info('Creating roles...');
        $roles = collect(['admin', 'support', 'user', 'verkoper', 'contactpersoon'])
            ->map(fn ($name) => Role::firstOrCreate(['name' => $name]));

        // 2. Create main admin user
        $admin = User::query()->firstOrCreate(
            ['email' => 'jayavandepol@hotmail.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // 3. Assign admin role
        if (! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        // 4. Run all seeders in correct order (dependencies matter!)
        $this->command->info('Running comprehensive seeders...');
        
        $this->call([
            UserSeeder::class,         // 5 Users with roles
            EventSeeder::class,        // 5 Events with different statuses
            VendorSeeder::class,       // 5 Vendors with specializations
            ContactPersonSeeder::class, // 5 ContactPersons linked to vendors
            StandSeeder::class,        // 5 Stands linked to events/vendors
            TicketSeeder::class,       // 5 Tickets with different types/statuses
        ]);

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('📊 Created data summary:');
        $this->command->line('   • 5+ Users (with roles)');
        $this->command->line('   • 5 Events (various statuses)');
        $this->command->line('   • 5 Vendors (with specializations)');
        $this->command->line('   • 5 Contact Persons (linked to vendors)');
        $this->command->line('   • 5 Stands (linked to events/vendors)');
        $this->command->line('   • 5 Tickets (various types and statuses)');
    }
}
