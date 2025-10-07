<?php

namespace App\Filament\Resources\Tickets\TicketResource\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    /** Beperk wat een niet-admin mag wijzigen & bereken total. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();
        if (! $user->hasAnyRole(['admin','support'])) {
            unset($data['status']); // Status alleen voor staff
        }
        if (isset($data['quantity'], $data['unit_price'])) {
            $data['total_price'] = $data['quantity'] * $data['unit_price'];
        }
        return $data;
    }
}
