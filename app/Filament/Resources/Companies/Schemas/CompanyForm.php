<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
    /** Currencies offered when creating a company. */
    public const CURRENCIES = [
        'USD' => 'USD — US dollar', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — Pound sterling',
        'KES' => 'KES — Kenyan shilling', 'TZS' => 'TZS — Tanzanian shilling', 'UGX' => 'UGX — Ugandan shilling',
        'NGN' => 'NGN — Nigerian naira', 'GHS' => 'GHS — Ghanaian cedi', 'ZAR' => 'ZAR — South African rand',
        'INR' => 'INR — Indian rupee', 'AED' => 'AED — UAE dirham',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company profile')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('registration_number')->maxLength(255)->unique(ignoreRecord: true),
                TextInput::make('email')->label('Email address')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(50),
                Textarea::make('address')->rows(2)->columnSpanFull(),
                FileUpload::make('logo_path')->label('Logo')->image()->disk('public')->directory('company-logos')
                    ->maxSize(2048)->columnSpanFull(),
            ]),
            Section::make('Business settings')->columns(2)->schema([
                Select::make('currency')->options(self::CURRENCIES)->required()->default('USD')->searchable(),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Inactive companies cannot be entered by any user.'),
                Toggle::make('settings.allow_negative_stock')->label('Allow selling beyond available stock')
                    ->helperText('Off by default. When on, sales may take stock below zero.')->columnSpanFull(),
            ]),
        ]);
    }
}
