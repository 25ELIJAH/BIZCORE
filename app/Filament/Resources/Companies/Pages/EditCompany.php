<?php

namespace App\Filament\Resources\Companies\Pages;

use App\Filament\Resources\Companies\CompanyResource;
use App\Services\ActivityLogger;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function afterSave(): void
    {
        app(ActivityLogger::class)->log(
            'company.updated',
            $this->record,
            ['changes' => $this->record->getChanges()],
            companyId: $this->record->id,
        );
    }
}
