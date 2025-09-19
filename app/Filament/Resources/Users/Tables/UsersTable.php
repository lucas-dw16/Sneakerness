<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor.company_name')
                    ->label('Vendor')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                BadgeColumn::make('roles.name')
                    ->label('Rollen')
                    ->separator(', ')
                    ->colors([
                        'primary',
                        'success' => fn ($state): bool => in_array($state, ['admin']),
                        'warning' => fn ($state): bool => in_array($state, ['support', 'verkoper']),
                        'info' => fn ($state): bool => in_array($state, ['contactpersoon']),
                    ])
                    ->icon(fn ($state) => match ($state) {
                        'admin' => 'heroicon-o-shield-check',
                        'support' => 'heroicon-o-lifebuoy',
                        'verkoper' => 'heroicon-o-currency-dollar',
                        'contactpersoon' => 'heroicon-o-user-group',
                        default => null,
                    })
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Rol')
                    ->placeholder('Filter op rol')
                    ->multiple(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn () => Auth::user()?->hasRole('admin')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
