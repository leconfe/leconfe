<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\Roles\RoleCreateAction;
use App\Actions\Roles\RoleUpdateAction;
use App\Models\Enums\UserRole;
use App\Models\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
        $default = array_keys(Role::getDefaultPermissionsAttribute());

        $permissionOptions = Role::whereNot('name', UserRole::Admin)
            ->whereIn('name', $default)
            ->pluck('name', 'name')
            ->toArray();

        return $table
            ->query($this->getQuery($default))
            ->heading(__('general.roles'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(),
                TextColumn::make('meta.permission_level')
                    ->label(__('general.permission_level'))
                    ->getStateUsing(fn(Role $record) => $record->getMeta('permission_level'))
                    ->searchable(false),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('general.edit'))
                    ->modalWidth(MaxWidth::Large)
                    ->hidden(fn(Role $record) => in_array($record->name, $default))
                    ->fillForm(function (Role $record) {
                        return [
                            ...$record->toArray(),
                            'meta' => $record->getAllMeta()->toArray(),
                        ];
                    })
                    ->form([
                        TextInput::make('name')
                            ->label(__('general.name'))
                            ->required(),
                        Select::make('meta.permission_level')
                            ->default(fn($record) => $record->name)
                            ->label(__('general.permission_level'))
                            ->options($permissionOptions)
                            ->required(),
                    ])
                    ->action(fn(Role $record, array $data) => RoleUpdateAction::run($record, [
                        'name' => $data['name'],
                        'meta' => $data['meta'],
                        'permissions' => Role::getPermissionsForRole($data['meta']['permission_level']),
                    ]))
                    ->successNotificationTitle(__('general.role_updated')),
                DeleteAction::make()
                    ->label(__('general.delete'))
                    ->requiresConfirmation()
                    ->hidden(fn(Role $record) => in_array($record->name, $default))
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
                            ->options($permissionOptions)
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        RoleCreateAction::run([
                            'name' => $data['name'],
                            'meta' => $data['meta'],
                            'scheduled_conference_id' => app()->isOnScheduledConference() ? app()->getCurrentScheduledConference()->id : 0,
                            'permissions' => Role::getPermissionsForRole($data['meta']['permission_level']),
                        ]);
                    })
                    ->successNotificationTitle(__('general.role_created')),
            ])
            ->emptyStateHeading(__('general.no_roles'));
    }

    protected function getQuery($defaultRole): Builder
    {
        $query = Role::query()->with('meta');
        $query->whereNotIn('name', $defaultRole);

        return $query;
    }
}

