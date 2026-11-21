<?php

namespace App\Filament\Resources\Companies\Tables;

use App\Models\Company;
use App\Services\ActivityLogger;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * Companies are deactivated, never deleted: deleting would orphan years of
 * financial and stock history.
 */
class CompaniesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('registration_number')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('currency'),
                TextColumn::make('users_count')->counts('users')->label('Users'),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('created_at')->label('Created')->date()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->trueLabel('Active')->falseLabel('Inactive'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::toggleActiveAction(),
            ])
            ->defaultSort('name');
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (Company $record) => $record->is_active ? 'Deactivate' : 'Activate')
            ->color(fn (Company $record) => $record->is_active ? 'danger' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (Company $record) => $record->is_active
                ? 'Users will no longer be able to enter this company. Its data is kept.'
                : 'Users assigned to this company will be able to enter it again.')
            ->action(function (Company $record) {
                $record->update(['is_active' => ! $record->is_active]);
                app(ActivityLogger::class)->log(
                    $record->is_active ? 'company.activated' : 'company.deactivated',
                    $record,
                    companyId: $record->id,
                );
            });
    }
}
