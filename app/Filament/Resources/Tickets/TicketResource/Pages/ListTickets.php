<?php

namespace App\Filament\Resources\Tickets\TicketResource\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    /** Actieknoppen boven de lijst. */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Koop Tickets')
                ->visible(fn () => Auth::check()),
        ];
    }
}
