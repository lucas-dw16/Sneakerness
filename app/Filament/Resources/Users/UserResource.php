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
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // Provide navigation group via accessor to avoid strict property typing issues
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'User Management';
    }

    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'user';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /* -------------------------------------------------
     |  Authorization (Spatie Roles)
     |  Adjust role names to match your seeding.
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
        // Only admins may create users.
        return self::currentUserHas(['admin']);
    }

    public static function canEdit($record): bool
    {
        // Only admins may edit users.
        return self::currentUserHas(['admin']);
    }

    public static function canDelete($record): bool
    {
        // Only admins may delete users.
        return self::currentUserHas(['admin']);
    }

    public static function canForceDelete($record): bool
    {
        return false; // Usually not needed
    }

    public static function canDeleteAny(): bool
    {
        return self::currentUserHas(['admin']);
    }

    public static function canRestore($record): bool
    {
        return false; // If using soft deletes, adjust
    }

    /* -------------------------------------------------
     |  Navigation visibility
     |-------------------------------------------------- */
    public static function shouldRegisterNavigation(): bool
    {
        // Only show in sidebar for staff roles
        return self::currentUserHas(['admin', 'support']);
    }

    public static function getNavigationBadge(): ?string
    {
        // Show total users (admins only to avoid leaking data to support if desired)
        return self::currentUserHas(['admin']) ? (string) User::count() : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    /* -------------------------------------------------
     |  Global Search (optional attributes)
     |-------------------------------------------------- */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
