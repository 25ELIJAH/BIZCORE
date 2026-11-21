<?php

namespace App\Filament\Resources\Companies\RelationManagers;

use App\Models\Role;
use App\Services\ActivityLogger;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Assigns users to this company with a role that applies only here
 * (company_user.role_id), so one person can hold different roles in different companies.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Users and roles';

    private static function roleSelect(): Select
    {
        return Select::make('role_id')->label('Role in this company')->options(fn () => Role::orderBy('name')->pluck('name', 'id'))->required();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([self::roleSelect()]);
    }

    public function table(Table $table): Table
    {
        $log = fn (string $action, $user, array $props = []) => app(ActivityLogger::class)
            ->log($action, $user, $props, companyId: $this->getOwnerRecord()->id);

        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->label('Role')
                    ->state(fn ($record) => Role::find($record->pivot->role_id)?->name ?? '—')->badge(),
            ])
            ->headerActions([
                AttachAction::make()->label('Assign user')
                    ->preloadRecordSelect()
                    ->schema(fn (AttachAction $action): array => [$action->getRecordSelect(), self::roleSelect()])
                    ->after(fn ($record, array $data) => $log('company.user_assigned', $record, ['role_id' => $data['role_id'] ?? null])),
            ])
            ->recordActions([
                EditAction::make()->label('Change role')
                    ->fillForm(fn ($record) => ['role_id' => $record->pivot->role_id])
                    ->using(function ($record, array $data) use ($log) {
                        $this->getOwnerRecord()->users()->updateExistingPivot($record->id, ['role_id' => $data['role_id']]);
                        $log('company.user_role_changed', $record, ['role_id' => $data['role_id']]);

                        return $record;
                    }),
                DetachAction::make()->label('Remove')
                    ->after(fn ($record) => $log('company.user_removed', $record)),
            ]);
    }
}
