<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyForm
{
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
            Section::make('Invoice details')->description('Printed on every invoice: bank details so customers know where to pay, and a website.')->columns(2)->schema([
                TextInput::make('settings.website')->label('Website')->maxLength(255)->placeholder('www.example.com'),
                TextInput::make('settings.bank_name')->label('Bank')->maxLength(120),
                TextInput::make('settings.bank_account_name')->label('Account name')->maxLength(120),
                TextInput::make('settings.bank_account_number')->label('Account number')->maxLength(60),
                TextInput::make('settings.bank_branch')->label('Branch')->maxLength(120),
                Textarea::make('settings.invoice_note')->label('Note at the bottom of invoices')->rows(2)->maxLength(300)->columnSpanFull(),
            ]),
            Section::make('Business settings')->columns(2)->schema([
                TextInput::make('currency_display')->label('Currency')->default('KES — Kenyan shilling')->disabled()->dehydrated(false)
                    ->helperText('BizCore uses Kenyan shillings for every company.'),
                Toggle::make('is_active')->label('Active')->default(true)
                    ->helperText('Inactive companies cannot be entered by any user.'),
                Toggle::make('settings.allow_negative_stock')->label('Allow selling beyond available stock')
                    ->helperText('Off by default. When on, sales may take stock below zero.')->columnSpanFull(),
            ]),
        ]);
    }
}
