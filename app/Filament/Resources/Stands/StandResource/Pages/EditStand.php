<?php

namespace App\Filament\Resources\Stands\StandResource\Pages;

use App\Filament\Resources\Stands\StandResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditStand extends EditRecord
{
    protected static string $resource = StandResource::class;

    public static function canEdit($record): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole(['admin','support']);
    }
}
