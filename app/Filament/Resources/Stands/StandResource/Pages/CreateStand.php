<?php

namespace App\Filament\Resources\Stands\StandResource\Pages;

use App\Filament\Resources\Stands\StandResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateStand extends CreateRecord
{
    protected static string $resource = StandResource::class;

    public static function canCreate(): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole(['admin','support']);
    }
}
