<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;

class CreateCompany extends CreateRecord
{
    protected static string $resource = CompanyResource::class;

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->log(
            'company.created',
            $this->record,
            ['new' => $this->record->only(['name', 'registration_number', 'currency'])],
            companyId: $this->record->id,
        );
    }
}
