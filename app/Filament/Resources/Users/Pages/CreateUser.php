<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** is_active / is_super_admin are deliberately not mass-assignable on the model; set them explicitly here. */
    protected function handleRecordCreation(array $data): Model
    {
        $user = new User;
        $user->forceFill($data)->save();

        return $user;
    }

    protected function afterCreate(): void
    {
        app(ActivityLogger::class)->log('user.created', $this->record, [
            'new' => $this->record->only(['name', 'email', 'is_active', 'is_super_admin']),
        ]);
    }
}
