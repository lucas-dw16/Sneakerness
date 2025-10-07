<?php

namespace App\Filament\Resources\Tickets\TicketResource\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTicket extends CreateRecord
{
    /** Resource klasse koppeling. */
    protected static string $resource = TicketResource::class;

    /**
     * Mutaties vóór het opslaan: koppel user, bereken total, forceer status.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        $data['user_id'] = $user->id;
        if (! isset($data['total_price']) && isset($data['quantity'], $data['unit_price'])) {
            $data['total_price'] = $data['quantity'] * $data['unit_price'];
        }
        if (! $user->hasAnyRole(['admin','support'])) {
            $data['status'] = 'pending';
        }
        return $data;
    }
}
