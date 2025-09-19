<?php

namespace App\Filament\Resources\Contacts\Pages;

use App\Filament\Resources\Contacts\ContactPersonResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateContactPerson extends CreateRecord
{
    protected static string $resource = ContactPersonResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove non-model field
        unset($data['user_password']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record; // ContactPerson
        $state = $this->form->getState();
        $email = $record->email; // same as form email
        $password = $state['user_password'] ?? null;
        $name = $record->name;

        if (! $email || ! $password) {
            return;
        }

        $existing = User::where('email', $email)->first();
        if ($existing) {
            if (! $existing->vendor_id && $record->vendor_id) {
                $existing->vendor_id = $record->vendor_id;
                $existing->save();
            }
            if (! $existing->hasRole('contactpersoon')) {
                $existing->assignRole('contactpersoon');
            }
            return;
        }

        DB::transaction(function () use ($record, $name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'vendor_id' => $record->vendor_id,
            ]);
            $user->assignRole('contactpersoon');
        });
    }
}
