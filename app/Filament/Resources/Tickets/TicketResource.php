<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\TicketResource\Pages;
use App\Models\Ticket;
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
    protected static ?string $model = Ticket::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftEllipsis;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Support';
    }

    protected static ?int $navigationSort = 80;

    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::userHas(['admin','support','verkoper','contactpersoon','user']);
    }

    public static function canCreate(): bool
    {
        // All authenticated roles can create a ticket
        return Auth::check();
    }

    public static function canEdit($record): bool
    {
        if (self::userHas(['admin','support'])) return true;
        $u = Auth::user();
        if (! $u) return false;
        // Owner may edit only while open/in_progress
        return $record->user_id === $u->id && in_array($record->status, ['open','in_progress']);
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
            TextInput::make('subject')->required()->maxLength(255),
            Textarea::make('description')->required()->rows(6),
            Select::make('priority')->options([
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent',
            ])->default('normal')->required(),
            Select::make('status')->options([
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'resolved' => 'Resolved',
                'closed' => 'Closed',
            ])->default('open')
                ->visible(fn () => self::userHas(['admin','support']))
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('subject')->searchable()->label('Onderwerp')->limit(40),
            BadgeColumn::make('status')->colors([
                'warning' => 'open',
                'info' => 'in_progress',
                'success' => 'resolved',
                'gray' => 'closed',
            ])->label('Status'),
            BadgeColumn::make('priority')->colors([
                'success' => 'low',
                'primary' => 'normal',
                'warning' => 'high',
                'danger' => 'urgent',
            ])->label('Prioriteit'),
            TextColumn::make('user.name')->label('Aangemaakt door')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('vendor.company_name')->label('Vendor')->toggleable(),
            TextColumn::make('created_at')->since()->label('Aangemaakt'),
        ])->filters([
            SelectFilter::make('status')->options([
                'open' => 'Open',
                'in_progress' => 'In Progress',
                'resolved' => 'Resolved',
                'closed' => 'Closed',
            ]),
            SelectFilter::make('priority')->options([
                'low' => 'Low',
                'normal' => 'Normal',
                'high' => 'High',
                'urgent' => 'Urgent',
            ]),
        ])->recordActions([
            EditAction::make()
                ->label('Open')
                ->visible(fn ($record) => self::canEdit($record)),
        ])->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $u = Auth::user();
        if (! $u) return $query->whereRaw('1=0');
        if ($u->hasAnyRole(['admin','support'])) return $query;
        if ($u->hasAnyRole(['verkoper','contactpersoon'])) {
            return $query->where('vendor_id', $u->vendor_id);
        }
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
