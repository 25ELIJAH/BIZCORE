<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/** Users are deactivated, never deleted, so audit trails keep pointing at a real person. */
class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('companies.name')->label('Companies')->badge(),
                IconColumn::make('is_super_admin')->label('Super admin')->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_login_at')->label('Last login')->dateTime()->sortable()->placeholder('Never'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->trueLabel('Active')->falseLabel('Inactive'),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('name');
    }
}
