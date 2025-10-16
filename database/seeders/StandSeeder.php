<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stand;
use App\Models\Event;
use App\Models\Vendor;

/**
 * StandSeeder - Seeded stands voor ontwikkeling en testing.
 * 
 * Creëert 5 sample stands gekoppeld aan events en vendors.
 */
class StandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Ensure we have events and vendors first
        $events = Event::all();
        $vendors = Vendor::all();
        
        if ($events->count() < 1) {
            $this->command->error('Geen events gevonden. Run EventSeeder eerst.');
            return;
        }
        
        if ($vendors->count() < 3) {
            $this->command->error('Niet genoeg vendors gevonden. Run VendorSeeder eerst.');
            return;
        }

        $stands = [
            [
                'number' => 'A-101',
                'location' => 'Hal A - Hoofdingang',
                'description' => 'Premium hoekstand met extra zichtbaarheid en ruimte voor displays.',
                'size' => '6m x 4m',
                'price' => 1250.00,
                'event_id' => $events->where('name', 'Sneaker Expo Amsterdam 2025')->first()?->id ?? $events->first()->id,
                'vendor_id' => $vendors->where('company_name', 'Sneaker Palace')->first()?->id,
            ],
            [
                'number' => 'A-205',
                'location' => 'Hal A - Centrale gang',
                'description' => 'Standaard stand in drukbezochte zone, ideaal voor vintage collecties.',
                'size' => '3m x 3m',
                'price' => 850.00,
                'event_id' => $events->where('name', 'Sneaker Expo Amsterdam 2025')->first()?->id ?? $events->first()->id,
                'vendor_id' => $vendors->where('company_name', 'Vintage Sole Store')->first()?->id,
            ],
            [
                'number' => 'B-112',
                'location' => 'Hal B - Fashion zone',
                'description' => 'Stand in de streetwear sectie met moderne uitstraling en LED verlichting.',
                'size' => '4m x 3m',
                'price' => 950.00,
                'event_id' => $events->where('name', 'Rotterdam Streetwear Festival')->first()?->id ?? $events->skip(1)->first()->id,
                'vendor_id' => $vendors->where('company_name', 'Streetwear Central')->first()?->id,
            ],
            [
                'number' => 'C-089',
                'location' => 'Hal C - Dames sectie',
                'description' => 'Speciale stand ingericht voor dames sneakers en lifestyle producten.',
                'size' => '3m x 2m',
                'price' => 650.00,
                'event_id' => $events->where('name', 'Vintage Kicks Market')->first()?->id ?? $events->skip(2)->first()->id,
                'vendor_id' => $vendors->where('company_name', 'Sole Sisters')->first()?->id,
            ],
            [
                'number' => 'D-034',
                'location' => 'Hal D - Workshop area',
                'description' => 'Stand met werkbank voor live customization en demonstraties.',
                'size' => '5m x 3m',
                'price' => 1100.00,
                'event_id' => $events->where('name', 'Sneaker Summit Den Haag')->first()?->id ?? $events->skip(3)->first()->id,
                'vendor_id' => null, // Beschikbare stand
            ],
        ];

        foreach ($stands as $standData) {
            // Use firstOrCreate to avoid duplicates
            Stand::firstOrCreate(
                [
                    'number' => $standData['number'],
                    'event_id' => $standData['event_id']
                ],
                $standData
            );
        }

        $this->command->info('✓ 5 Stands aangemaakt');
    }
}
