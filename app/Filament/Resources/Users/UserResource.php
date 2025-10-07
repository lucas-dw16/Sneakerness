<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum; // for signature compatibility if needed

class UserResource extends Resource
{
    /**
     * Het Eloquent model dat door deze resource beheerd wordt.
     */
    protected static ?string $model = User::class;

    /**
     * Navigatie icoon in het Filament panel.
     */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * Groepering in het navigatiemenu.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'User Management';
    }

    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Users';
    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Formulierdefinitie (gedelegeerd aan aparte configuratieklasse).
     */
    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    /**
     * Tabeldefinitie (kolommen, filters enz). Uitbesteed voor overzichtelijkheid.
     */
    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /* -------------------------------------------------
     |  Autorisatie (Spatie Rollen)
     |-------------------------------------------------- */
    protected static function currentUserHas(array $roles): bool
    {
        $user = Auth::user();
        return $user && $user->hasAnyRole($roles);
    }

    public static function canViewAny(): bool
    {
        return self::currentUserHas(['admin', 'support']);
    }

    public static function canCreate(): bool
    {
        return self::currentUserHas(['admin']);
    }

    public static function canEdit($record): bool
    {
        return self::currentUserHas(['admin']);
    }

    public static function canDelete($record): bool
    {
        return self::currentUserHas(['admin']);
    }

    public static function canForceDelete($record): bool
    {
        return false; // Geen permanente delete acties nodig hier.
    }

    public static function canDeleteAny(): bool
    {
        return self::currentUserHas(['admin']);
    }

    public static function canRestore($record): bool
    {
        return false; // Aanpassen indien soft deletes geactiveerd worden.
    }

    /* -------------------------------------------------
     |  Navigatie
     |-------------------------------------------------- */
    public static function shouldRegisterNavigation(): bool
    {
        return self::currentUserHas(['admin', 'support']);
    }

    public static function getNavigationBadge(): ?string
    {
        return self::currentUserHas(['admin']) ? (string) User::count() : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    /* -------------------------------------------------
     |  Global Search
     |-------------------------------------------------- */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getRelations(): array
    {
        return [
            // Relaties / relation managers kunnen hier toegevoegd worden.
        ];
    }

    /**
     * Routes naar de Filament pagina klassen.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
