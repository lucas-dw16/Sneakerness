<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactPersonResource;
use Filament\Resources\Pages\CreateRecord;

class CreateContactPerson extends CreateRecord
{
    protected static string $resource = ContactPersonResource::class;
}
