<?php

namespace App\Filament\Resources\Tickets\TicketResource\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();
        $data['user_id'] = $user->id;
        // If user linked to a vendor (verkoper/contactpersoon) attach vendor_id
        if ($user->vendor_id) {
            $data['vendor_id'] = $user->vendor_id;
        }
        // Force status open for non support/admin
        if (! $user->hasAnyRole(['admin','support'])) {
            $data['status'] = 'open';
        }
        return $data;
    }
}
