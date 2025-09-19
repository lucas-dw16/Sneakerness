<?php

namespace App\Filament\Resources\Stands\StandResource\Pages;

use App\Filament\Resources\Stands\StandResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;

class ListStands extends ListRecords
{
    protected static string $resource = StandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn () => Auth::user()?->hasAnyRole(['admin','support'])),
        ];
    }
}
