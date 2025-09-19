<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $actions = [];
        if ($user && $user->hasRole('admin')) {
            $actions[] = CreateAction::make();
        }
        return $actions;
    }
}
