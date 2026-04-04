<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\Roles\RoleCreateAction;
use App\Actions\Roles\RoleUpdateAction;
use App\Models\Enums\UserRole;
use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class UserRoleTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        $defaultRoles = array_keys(Role::getDefaultPermissionsAttribute());

        $permissionLevelOptions = [];
        if (app()->isOnScheduledConference()) {
            $permissionLevelOptions = collect(UserRole::scheduledConferenceRoles())
                ->map(fn($role) => $role instanceof \BackedEnum ? $role->value : $role)
                ->mapWithKeys(fn($role) => [$role => $role])
                ->toArray();
        } else {
            $permissionLevelOptions = collect(UserRole::conferenceRoles())
                ->map(fn($role) => $role instanceof \BackedEnum ? $role->value : $role)
                ->mapWithKeys(fn($role) => [$role => $role])
                ->toArray();
        }

        return $table
            ->query($this->getQuery())
            ->heading(__('general.roles'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(),
                TextColumn::make('meta.permission_level')
                    ->label(__('general.permission_level'))
                    ->getStateUsing(fn(Role $record) => $record->getMeta('permission_level') ?? $record->name)
                    ->searchable(false),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label(__('general.users'))
                    ->badge()
            ])
            ->actions([
                EditAction::make()
                    ->label(__('general.edit'))
                    ->modalWidth(MaxWidth::Large)
                    ->hidden(fn(Role $record) => in_array($record->name, $defaultRoles))
                    ->fillForm(function (Role $record) {
                        $meta = $record->getAllMeta()->toArray();
                        if (!isset($meta['permission_level'])) {
                            $meta['permission_level'] = $record->name;
                        }

                        return [
                            'name' => $record->name,
                            'meta' => $meta,
                        ];
                    })
                    ->form([
                        TextInput::make('name')
                            ->label(__('general.name'))
                            ->required(),
                        Select::make('meta.permission_level')
                            ->label(__('general.permission_level'))
                            ->options($permissionLevelOptions)
                            ->required()
                            ->disabled(true),
                    ])
                    ->action(function (Role $record, array $data) {
                        $meta = $data['meta'] ?? [];
                        $meta['permission_level'] = $meta['permission_level'] ?? $record->getMeta('permission_level') ?? $record->name;

                        RoleUpdateAction::run($record, [
                            'name' => $data['name'],
                            'meta' => $meta,
                        ]);
                    })
                    ->successNotificationTitle(__('general.role_updated')),
                DeleteAction::make()
                    ->label(__('general.delete'))
                    ->requiresConfirmation()
                    ->action(function (Role $record) {
                        if ($record->users_count > 0) {
                            Notification::make()
                                ->title(__('general.failed'))
                                ->body(__('general.cannot_delete_this_role_because_it_is_still_assigned_to_users'))
                                ->danger()
                                ->send();
                            return;
                        }
                        $record->delete();
                    })
                    ->hidden(fn(Role $record) => in_array($record->name, $defaultRoles))
                    ->successNotificationTitle(__('general.role_deleted')),
            ])
            ->headerActions([
                Action::make('createRole')
                    ->label(__('general.new_role'))
                    ->icon('heroicon-o-plus')
                    ->modalWidth(MaxWidth::Large)
                    ->form([
                        TextInput::make('name')
                            ->label(__('general.name'))
                            ->required(),
                        Select::make('meta.permission_level')
                            ->label(__('general.permission_level'))
                            ->options($permissionLevelOptions)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        RoleCreateAction::run([
                            'name' => $data['name'],
                            'meta' => $data['meta'],
                            'scheduled_conference_id' => app()->isOnScheduledConference() ? app()->getCurrentScheduledConference()->id : 0,
                        ]);
                    })
                    ->successNotificationTitle(__('general.role_created')),
            ])
            ->emptyStateHeading(__('general.no_roles'));
    }

    protected function getQuery(): Builder
    {
        return Role::query()
            ->with('meta')
            ->withoutGlobalScopes()
            ->availableRolesByContext();
    }
}

