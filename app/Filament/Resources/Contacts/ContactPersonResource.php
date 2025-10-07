<?php

namespace App\Filament\Resources\Contacts;

use App\Filament\Resources\Contacts\Pages\CreateContactPerson;
use App\Filament\Resources\Contacts\Pages\EditContactPerson;
use App\Filament\Resources\Contacts\Pages\ListContactPeople;
use App\Models\ContactPerson;
use App\Models\Vendor;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ContactPersonResource extends Resource
{
    /** Model dat wordt beheerd. */
    protected static ?string $model = ContactPerson::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Vendor Management';
    }

    /** Formulier voor create / edit. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vendor_id')
                ->label('Vendor')
                ->relationship('vendor', 'company_name')
                ->required()
                ->searchable()
                ->preload()
                ->visible(fn () => Auth::user()?->hasRole('admin') || Auth::user()?->hasRole('support')),
            TextInput::make('name')->required()->maxLength(150),
            TextInput::make('email')->email()->required(),
            // Wachtwoord alleen in form state; gebruiker wordt na create automatisch aangemaakt / geüpdatet.
            TextInput::make('user_password')
                ->label('Account wachtwoord')
                ->password()
                ->required()
                ->revealable()
                ->helperText('Wachtwoord voor automatisch contactpersoon account.')
                ->dehydrated(false),
            TextInput::make('phone')->maxLength(50),
            TextInput::make('role_label')->label('Rol')->maxLength(100),
            Toggle::make('is_primary')->label('Primair'),
        ]);
    }

    /** Tabel definitie voor index lijst. */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->label('Naam'),
                TextColumn::make('vendor.company_name')->label('Vendor')->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone')->toggleable(isToggledHiddenByDefault: true),
                BadgeColumn::make('role_label')->label('Rol'),
                BadgeColumn::make('is_primary')->label('Primair')->colors([
                    'success' => fn ($state) => (bool)$state,
                    'gray' => fn ($state) => ! $state,
                ])->formatStateUsing(fn ($state) => $state ? 'Ja' : 'Nee'),
            ])
            ->filters([
                SelectFilter::make('vendor_id')->relationship('vendor', 'company_name')->label('Vendor'),
            ])
            ->recordUrl(fn ($record) => static::canEdit($record) ? static::getUrl('edit', ['record' => $record]) : null);
    }

    /** Hulpmethode voor role checks. */
    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    public static function canViewAny(): bool
    {
        if (self::userHas(['admin', 'support'])) return true;
        if (self::userHas(['verkoper'])) return true; // In toekomst scoping naar eigen vendor
        return false;
    }

    public static function canView($record): bool
    {
        if (self::userHas(['admin', 'support'])) return true;
        if (self::userHas(['verkoper'])) {
            return Auth::user()->vendor_id === $record->vendor_id;
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
            'index' => ListContactPeople::route('/'),
            'create' => CreateContactPerson::route('/create'),
            'edit' => EditContactPerson::route('/{record}/edit'),
        ];
    }
}
