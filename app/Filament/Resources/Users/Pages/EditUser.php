<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\ActivityLogger;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->forceFill($data);
        $changes = $record->getDirty();
        $old = array_intersect_key($record->getOriginal(), $changes);
        $record->save();

        // The logger redacts the password key; never record the hash itself.
        unset($changes['password'], $old['password']);
        app(ActivityLogger::class)->log('user.updated', $record, ['old' => $old, 'new' => $changes, 'password_changed' => isset($data['password'])]);

        return $record;
    }
}
