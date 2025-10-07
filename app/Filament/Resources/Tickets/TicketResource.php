<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\Event;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TicketResource extends Resource
{
    /** Ticket aankopen voor events (géén support). */
    protected static ?string $model = Ticket::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket; // ander passend icoon

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Event Management';
    }

    protected static ?int $navigationSort = 90;

    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Iedere ingelogde rol die tickets kan kopen mag het menu zien.
        return self::userHas(['admin','support','verkoper','contactpersoon','user']);
    }

    public static function canCreate(): bool
    {
        return Auth::check();
    }

    public static function canEdit($record): bool
    {
        // Alleen bewerkbaar zolang status 'pending' is en eigenaar of admin/support.
        if (self::userHas(['admin','support'])) return true;
        $u = Auth::user();
        return $u && $record->user_id === $u->id && $record->status === 'pending';
    }

    public static function canDelete($record): bool
    {
        return self::userHas(['admin']);
    }

    public static function canDeleteAny(): bool
    {
        return self::userHas(['admin']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('event_id')
                ->label('Event')
                ->relationship('event','name')
                ->required()
                ->searchable()
                ->preload(),
            TextInput::make('quantity')
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->required()
                ->label('Aantal'),
            TextInput::make('unit_price')
                ->numeric()
                ->step('0.01')
                ->required()
                ->prefix('€')
                ->label('Prijs per stuk'),
            TextInput::make('total_price')
                ->disabled()
                ->dehydrated()
                ->numeric()
                ->label('Totaal (€)')
                ->helperText('Wordt automatisch berekend (aantal × prijs).'),
            Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'paid' => 'Paid',
                    'cancelled' => 'Cancelled',
                ])
                ->default('pending')
                ->visible(fn () => self::userHas(['admin','support']))
                ->label('Status'),
            Select::make('type')
                ->options([
                    'regular' => 'Regular',
                    'vip' => 'VIP',
                ])->default('regular')->label('Type'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('event.name')->label('Event')->searchable(),
            TextColumn::make('user.name')->label('Koper')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('type')->label('Type')->badge()->color(fn($state) => $state === 'vip' ? 'warning' : 'primary'),
            TextColumn::make('quantity')->label('Aantal'),
            TextColumn::make('unit_price')->money('eur', true)->label('Prijs'),
            TextColumn::make('total_price')->money('eur', true)->label('Totaal'),
            BadgeColumn::make('status')->colors([
                'warning' => 'pending',
                'success' => 'paid',
                'danger' => 'cancelled',
            ])->label('Status'),
            TextColumn::make('created_at')->since()->label('Aangemaakt'),
        ])->filters([
            SelectFilter::make('status')->options([
                'pending' => 'Pending',
                'paid' => 'Paid',
                'cancelled' => 'Cancelled',
            ]),
            SelectFilter::make('type')->options([
                'regular' => 'Regular',
                'vip' => 'VIP',
            ])->label('Type'),
        ])->recordActions([
            EditAction::make()->visible(fn ($record) => self::canEdit($record)),
        ])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $u = Auth::user();
        if (! $u) return $query->whereRaw('1=0');
        if ($u->hasAnyRole(['admin','support'])) return $query; // alles
        // Overige rollen zien alleen hun eigen aankopen
        return $query->where('user_id', $u->id);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
        ];
    }
}
