<?php

namespace App\Filament\Resources\Events;

use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EventResource extends Resource
{
    /**
     * Het onderliggende Eloquent model voor deze Resource.
     */
    protected static ?string $model = Event::class;

    /**
     * Icon in de Filament navigatie.
     */
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    /**
     * Groep waaronder het item in de navigatie valt.
     */
    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return 'Event Management';
    }

    /**
     * Definitie van het formulier (create / edit) voor events.
     * We gebruiken hier componenten van Filament Forms.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Naam van het event - verplicht.
            TextInput::make('name')->required()->maxLength(150),
            // Slug wordt automatisch gevuld via model observer / mutator (niet bewerkbaar hier).
            TextInput::make('slug')
                ->disabled()
                ->dehydrated(false) // Niet opslaan omdat hij server-side gezet wordt.
                ->helperText('Wordt automatisch gegenereerd uit naam.'),
            // Status alleen zichtbaar voor admins zodat verkopers deze niet kunnen aanpassen.
            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'archived' => 'Archived',
                ])
                ->required()
                ->default('draft')
                ->visible(fn () => Auth::user()?->hasRole('admin')),
            DateTimePicker::make('starts_at')->required()->seconds(false),
            DateTimePicker::make('ends_at')->required()->seconds(false)
                ->rule('after:starts_at'), // Validatie: einddatum > startdatum
            TextInput::make('location')->maxLength(255),
            TextInput::make('capacity')->numeric()->minValue(0)->maxValue(500000)->nullable(),
            MarkdownEditor::make('description')
                ->toolbarButtons(['bold', 'italic', 'strike', 'bulletList', 'orderedList', 'link', 'preview'])
                ->nullable(),
        ]);
    }

    /**
     * Tabelconfiguratie voor index pagina.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'draft',
                        'success' => 'published',
                        'gray' => 'archived',
                    ])->label('Status'),
                TextColumn::make('starts_at')->dateTime('d-m-Y H:i')->sortable()->label('Start'),
                TextColumn::make('ends_at')->dateTime('d-m-Y H:i')->sortable()->label('Einde'),
                TextColumn::make('location')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('capacity')->numeric()->label('Cap.'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ]),
            ])
            ->recordActions([
                // Bewerken alleen voor admins.
                EditAction::make()->visible(fn () => Auth::user()?->hasRole('admin')),
            ])
            ->toolbarActions([
                // Extra toolbar acties kunnen hier toegevoegd worden.
            ]);
    }

    /**
     * Kleine helper om role-checks consistent te houden.
     */
    protected static function userHas(array $roles): bool
    {
        $u = Auth::user();
        return $u && $u->hasAnyRole($roles);
    }

    /**
     * Mag de gebruiker de lijst zien? (published filtering gebeurt elders bij view per record)
     */
    public static function canViewAny(): bool
    {
        return self::userHas(['admin', 'support', 'verkoper', 'contactpersoon']);
    }

    /**
     * Mag een individueel record bekeken worden.
     */
    public static function canView($record): bool
    {
        if (self::userHas(['admin', 'support'])) return true;
        if (self::userHas(['verkoper', 'contactpersoon'])) {
            return $record->status === 'published';
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

    /**
     * Zichtbaarheid in navigatie beperken tot staff.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return self::userHas(['admin', 'support']);
    }

    /**
     * Toon badge met aantal gepubliceerde events voor staff.
     */
    public static function getNavigationBadge(): ?string
    {
        if (self::userHas(['admin', 'support'])) {
            return (string) Event::where('status', 'published')->count();
        }
        return null;
    }

    /**
     * Welke attributen worden globaal doorzocht via Filament global search.
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'status', 'location'];
    }

    /**
     * Pagina's (routes) die bij deze resource horen.
     */
    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'create' => CreateEvent::route('/create'),
            'edit' => EditEvent::route('/{record}/edit'),
        ];
    }
}
