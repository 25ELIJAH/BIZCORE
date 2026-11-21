<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('password')->password()->revealable()
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn ($state) => filled($state))
                    ->rule(Password::min(8)->letters()->numbers())
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Leave blank to keep the current password.' : 'At least 8 characters with letters and numbers.'),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Inactive users cannot sign in.'),
                Toggle::make('is_super_admin')->label('Super administrator')
                    ->helperText('Can enter every company and use this administration panel. Company roles are assigned from each company page.')
                    ->columnSpanFull(),
            ]),
        ]);
    }
}
