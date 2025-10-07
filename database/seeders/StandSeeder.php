<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stand;
use App\Models\Event;
use App\Models\Vendor;
use App\Models\Ticket;
use App\Models\User;

class StandSeeder extends Seeder
{
    /**
     * Seed voorbeeld stands + enkele ticket aankopen.
     */
    public function run(): void
    {
        $event = Event::query()->first();
        $vendor = Vendor::query()->first();
        $buyer  = User::query()->whereHas('roles', fn($q) => $q->where('name','user'))->first();

        if (! $event || ! $vendor) {
            return; // vereiste basisdata ontbreekt
        }

        // Maak enkele stands indien geen bestaan
        if (! Stand::query()->exists()) {
            $stands = [
                ['name' => 'Hall A - Premium Corner', 'location' => 'Hal A', 'size_sqm' => 30, 'price_eur' => 1500, 'event_id' => $event->id, 'vendor_id' => $vendor->id],
                ['name' => 'Hall A - Inline 12', 'location' => 'Hal A', 'size_sqm' => 12, 'price_eur' => 600, 'event_id' => $event->id, 'vendor_id' => $vendor->id],
                ['name' => 'Hall B - Starter 6', 'location' => 'Hal B', 'size_sqm' => 6, 'price_eur' => 350, 'event_id' => $event->id, 'vendor_id' => null],
            ];
            foreach ($stands as $s) {
                Stand::create($s);
            }
        }

        // Voorbeeld ticket aankopen (alleen indien buyer beschikbaar en geen tickets)
        if ($buyer && ! Ticket::query()->exists()) {
            Ticket::create([
                'user_id' => $buyer->id,
                'event_id' => $event->id,
                'type' => 'regular',
                'quantity' => 2,
                'unit_price' => 25.00,
                'status' => 'paid',
            ]);
            Ticket::create([
                'user_id' => $buyer->id,
                'event_id' => $event->id,
                'type' => 'vip',
                'quantity' => 1,
                'unit_price' => 60.00,
                'status' => 'pending',
            ]);
        }
    }
}
