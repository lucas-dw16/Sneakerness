<?php

namespace App\Filament\Resources\Vendors\Pages;

use App\Filament\Resources\Vendors\VendorResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Strip user fields from vendor persistence
        unset($data['user_password'], $data['user_email'], $data['user_name']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $vendor = $this->record;
        $name = $this->form->getState()['user_name'] ?: $vendor->company_name;
        $email = $this->form->getState()['user_email'];
        $password = $this->form->getState()['user_password'];

        if (! $email || ! $password) {
            return; // Should be required, but safeguard
        }

        // Avoid duplicate user if email already exists
        $existing = User::where('email', $email)->first();
        if ($existing) {
            // Link vendor if empty vendor_id
            if (! $existing->vendor_id) {
                $existing->vendor_id = $vendor->id;
                $existing->save();
            }
            if (! $existing->hasRole('verkoper')) {
                $existing->assignRole('verkoper');
            }
            return;
        }

        DB::transaction(function () use ($vendor, $name, $email, $password) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'vendor_id' => $vendor->id,
            ]);
            $user->assignRole('verkoper');
        });
    }
}
