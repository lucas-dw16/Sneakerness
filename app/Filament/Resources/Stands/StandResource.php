<?php

namespace App\Filament\Resources\Stands;

use App\Filament\Resources\Stands\StandResource\Pages; 
use App\Models\Stand; 
use BackedEnum; 
use Filament\Forms\Components\Select; 
use Filament\Forms\Components\TextInput; 
use Filament\Forms\Components\Toggle; 
use Filament\Resources\Resource; 
use Filament\Schemas\Schema; 
use Filament\Support\Icons\Heroicon; 
use Filament\Tables\Columns\BadgeColumn; 
use Filament\Tables\Columns\IconColumn; 
use Filament\Tables\Columns\TextColumn; 
use Filament\Tables\Filters\SelectFilter; 
use Filament\Tables\Filters\TernaryFilter; 
use Filament\Tables\Table; 
use Filament\Actions\EditAction; 
use Filament\Actions\DeleteBulkAction; 
use Illuminate\Database\Eloquent\Builder; 
use Illuminate\Support\Facades\Auth; 
use UnitEnum; 

class StandResource extends Resource
{
    /** Model voor deze resource. */
    protected static ?string $model = Stand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Event Management';
    }

    protected static ?int $navigationSort = 65; // Positie in navigatie

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();
        if (! $user) return false;
        return $user->hasAnyRole(['admin', 'support']);
    }

    /** Role helper. */
    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    public static function canViewAny(): bool
    {
        return self::userHas(['admin','support','verkoper','contactpersoon']);
    }

    public static function canView($record): bool
    {
        if (self::userHas(['admin','support'])) return true;
        if (self::userHas(['verkoper','contactpersoon'])) {
            return Auth::user()->vendor_id && $record->vendor_id === Auth::user()->vendor_id;
        }
        return false;
    }

    public static function canCreate(): bool
    {
        return self::userHas(['admin','support']);
    }

    public static function canEdit($record): bool
    {
        return self::userHas(['admin','support']);
    }

    public static function canDelete($record): bool
    {
        return self::userHas(['admin']);
    }

    public static function canDeleteAny(): bool
    {
        return self::userHas(['admin']);
    }

    /** Formulier: basisgegevens van een stand. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('event_id')
                ->relationship('event', 'name')
                ->searchable()
                ->required(),
            Select::make('vendor_id')
                ->relationship('vendor', 'company_name')
                ->searchable()
                ->visible(fn() => Auth::user()?->hasAnyRole(['admin','support']))
                ->helperText('Alleen beheerders kunnen direct een vendor koppelen.'),
            TextInput::make('name')->required(),
            TextInput::make('location'),
            TextInput::make('size_sqm')->numeric()->label('Oppervlakte (m²)'),
            TextInput::make('price_eur')->numeric()->label('Prijs (€)')->prefix('€'),
            Toggle::make('is_reserved')->label('Gereserveerd')
                ->visible(fn() => Auth::user()?->hasAnyRole(['admin','support'])),
        ])->columns(2);
    }

    /** Tabeloverzicht met filters. */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->label('Stand'),
                TextColumn::make('event.name')->label('Event')->toggleable(),
                TextColumn::make('vendor.company_name')->label('Vendor')->toggleable(),
                BadgeColumn::make('location')->colors(['primary']),
                TextColumn::make('size_sqm')->label('m²'),
                TextColumn::make('price_eur')->money('eur', true)->label('Prijs'),
                IconColumn::make('is_reserved')->boolean()->label('Res.'),
                TextColumn::make('updated_at')->since()->label('Bijgewerkt')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_reserved')->label('Gereserveerd'),
                SelectFilter::make('event_id')->relationship('event','name')->label('Event'),
                SelectFilter::make('vendor_id')->relationship('vendor','company_name')->label('Vendor')
                    ->visible(fn() => Auth::user()?->hasAnyRole(['admin','support'])),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => self::userHas(['admin','support'])),
            ])
            ->bulkActions([
                DeleteBulkAction::make()->visible(fn () => self::userHas(['admin'])),
            ]);
    }

    /** Query scoping per rol. */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        if ($user && $user->hasAnyRole(['verkoper','contactpersoon'])) {
            return $query->where('vendor_id', $user->vendor_id);
        }
        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStands::route('/'),
            'create' => Pages\CreateStand::route('/create'),
            'edit' => Pages\EditStand::route('/{record}/edit'),
        ];
    }
}
