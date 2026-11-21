<?php

namespace App\Filament\Resources\Companies\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CompanyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Company profile')->columns(2)->schema([
                TextEntry::make('name'),
                TextEntry::make('registration_number')->placeholder('—'),
                TextEntry::make('email')->label('Email address')->placeholder('—'),
                TextEntry::make('phone')->placeholder('—'),
                TextEntry::make('address')->placeholder('—')->columnSpanFull(),
                ImageEntry::make('logo_path')->label('Logo')->disk('public')->placeholder('—'),
                TextEntry::make('created_at')->label('Date created')->dateTime(),
            ]),
            Section::make('Status and settings')->columns(2)->schema([
                TextEntry::make('currency'),
                IconEntry::make('is_active')->label('Active')->boolean(),
                IconEntry::make('settings.allow_negative_stock')->label('Allows selling beyond stock')->boolean(),
            ]),
        ]);
    }
}
