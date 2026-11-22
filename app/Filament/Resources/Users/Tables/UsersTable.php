<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
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
                IconColumn::make('two_factor_confirmed_at')->label('2-step')->boolean()->getStateUsing(fn ($record) => (bool) $record->two_factor_confirmed_at),
                TextColumn::make('last_login_at')->label('Last login')->dateTime()->sortable()->placeholder('Never'),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Status')->trueLabel('Active')->falseLabel('Inactive'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reset2fa')
                    ->label('Reset 2-step')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('They will set up their authenticator app again at next sign-in. Use this when someone loses their phone and recovery codes.')
                    ->visible(fn ($record) => (bool) $record->two_factor_confirmed_at)
                    ->action(function ($record) {
                        $record->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
                        app(\App\Services\ActivityLogger::class)->log('account.two_factor_reset', $record);
                    }),
            ])
            ->defaultSort('name');
    }
}
