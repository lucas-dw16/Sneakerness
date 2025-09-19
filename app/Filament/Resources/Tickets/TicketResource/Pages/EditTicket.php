<?php

namespace App\Filament\Resources\Tickets\TicketResource\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();
        // Non support/admin cannot change status manually (status field hidden anyway)
        if (! $user->hasAnyRole(['admin','support'])) {
            unset($data['status']);
        }
        return $data;
    }

    protected function afterSave(): void
    {
        // Auto set resolved_at timestamp if status is resolved/closed
        $ticket = $this->record;
        if (in_array($ticket->status, ['resolved','closed']) && ! $ticket->resolved_at) {
            $ticket->resolved_at = now();
            $ticket->save();
        }
        if (! in_array($ticket->status, ['resolved','closed']) && $ticket->resolved_at) {
            $ticket->resolved_at = null;
            $ticket->save();
        }
    }
}
