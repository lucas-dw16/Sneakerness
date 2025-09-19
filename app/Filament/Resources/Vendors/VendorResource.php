<?php

namespace App\Filament\Resources\Vendors;

use App\Filament\Resources\Vendors\Pages\CreateVendor;
use App\Filament\Resources\Vendors\Pages\EditVendor;
use App\Filament\Resources\Vendors\Pages\ListVendors;
use App\Models\Vendor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Vendor Management';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('company_name')->required()->maxLength(255)->label('Bedrijfsnaam'),
            TextInput::make('user_name')
                ->label('Account naam')
                ->helperText('Naam voor het verkoper account (optioneel, standaard bedrijfsnaam).')
                ->maxLength(255)
                ->dehydrated(false), // keep false (optional, only needed in form state, not saved)
            TextInput::make('user_email')
                ->label('Account e-mail')
                ->email()
                ->helperText('E-mail voor automatisch gebruiker aanmaken (verkoper rol).')
                ->required()
                ->dehydrated(false),
            TextInput::make('user_password')
                ->label('Account wachtwoord')
                ->password()
                ->required()
                ->revealable()
                ->helperText('Wachtwoord voor nieuwe gebruiker (verkoper).')
                // keep in form state so afterCreate can read it but exclude from model
                ->dehydrated(false),
            Select::make('status')->options([
                'prospect' => 'Prospect',
                'confirmed' => 'Confirmed',
                'blacklisted' => 'Blacklisted',
            ])->default('prospect')->required()->visible(fn () => Auth::user()?->hasRole('admin')),
            TextInput::make('vat_number')->label('BTW nummer')->maxLength(50),
            TextInput::make('kvk_number')->label('KvK nummer')->maxLength(50),
            TextInput::make('billing_email')->email()->required()->label('Factuur e-mail'),
            TextInput::make('website')->url()->label('Website')->maxLength(255),
            Textarea::make('billing_address')->label('Factuur adres'),
            Textarea::make('notes')->label('Notities'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('company_name')->searchable()->sortable()->label('Bedrijfsnaam'),
                BadgeColumn::make('status')->colors([
                    'warning' => 'prospect',
                    'success' => 'confirmed',
                    'danger' => 'blacklisted',
                ])->label('Status'),
                TextColumn::make('billing_email')->label('Factuur e-mail'),
                TextColumn::make('website')->url(fn ($record) => $record->website)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'prospect' => 'Prospect',
                    'confirmed' => 'Confirmed',
                    'blacklisted' => 'Blacklisted',
                ])
            ])
            ->recordActions([]);
    }

    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    public static function canViewAny(): bool
    {
        // Admin & support; verkoper alleen eigen vendor (
        if (self::userHas(['admin', 'support'])) return true;
        if (self::userHas(['verkoper'])) return true; // we scope in query override (future)
        return false;
    }

    public static function canView($record): bool
    {
        if (self::userHas(['admin', 'support'])) return true;
        if (self::userHas(['verkoper'])) {
            return Auth::user()->vendor_id === $record->id;
        }
        return false;
    }

    public static function canCreate(): bool
    {
        return self::userHas(['admin']);
    }

    public static function canEdit($record): bool
    {
        return self::userHas(['admin']);
    }

    public static function canDelete($record): bool
    {
        return self::userHas(['admin']);
    }

    public static function canDeleteAny(): bool
    {
        return self::userHas(['admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userHas(['admin', 'support']);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendors::route('/'),
            'create' => CreateVendor::route('/create'),
            'edit' => EditVendor::route('/{record}/edit'),
        ];
    }
}
