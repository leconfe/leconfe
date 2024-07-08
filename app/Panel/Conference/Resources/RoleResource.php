<?php

namespace App\Panel\Conference\Resources;

use App\Actions\Roles\RolePersistAssignedPermissions;
use App\Models\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use App\Panel\Conference\Resources\RoleResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Livewire\Component as Livewire;
use Illuminate\Contracts\Support\Htmlable;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    public static function getNavigationLabel(): string
    {
        return __('translation.roleResource.getNavigationLabel');
    }
   


    protected static ?int $navigationSort = 6;

    protected static ?Role $parentRole = null;

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label(__('translation.roleResource.labelName')),
                        Select::make('copy_permissions_from')
                            ->dehydrated(false)
                            ->label(__('translation.roleResource.labelCopyPermissionsFrom'))
                            ->hidden(fn(string $operation) => $operation === 'view')
                            ->options(fn() => static::getEloquentQuery()->pluck('name', 'id')->toArray())
                            ->live()
                            ->afterStateUpdated(function (Set $set, ?string $state) {
                                if (! $state) {
                                    return;
                                }

                                /** @var Role */
                                $newParent = Role::find($state);

                                app(PermissionRegistrar::class)
                                    ->getPermissions()
                                    ->each(function (Permission $permission) use ($set, $newParent) {

                                        $condition = $newParent->hasPermissionTo($permission);

                                        $set('permissions.'.$permission->name, $condition);
                                    });
                            })
                            ->preload(),
                    ])
                    ->columns([
                        'sm' => 2,
                    ]),
                Section::make(__('translation.roleResource.labelAdvancedPermissions'))
                    ->schema(static::getPermissionEntitySchema())
                    ->collapsible(false)
                    ->columns([
                        'sm' => 4,
                    ]),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->label(__('translation.roleResource.labelName'))
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\Action::make('persistRolePermissions')
                    ->requiresConfirmation()
                    ->icon('heroicon-o-shield-check')
                    ->label(__('translation.roleResource.labelPersistPermissions'))
                    ->hidden(fn(Role $record) => !in_array($record->name, UserRole::values()) || app()->isProduction())
                    ->action(fn(Role $record) => RolePersistAssignedPermissions::run($record))
                    ->successNotificationTitle(__('translation.roleResource.successNotificationTitle'))
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
            'view' => Pages\ViewRole::route('/{record}'),
        ];
    }

    public static function getPermissionEntitySchema(): array
    {
        $permissions = app(PermissionRegistrar::class)->getPermissions();
        $permissionsGroupedByEntity = $permissions->groupBy(function ($permission) {
            // Split the permission name by the first colon
            // Example : "User:update" become ["User", "update"]
            [$context, $action] = explode(':', $permission->name);

            return $context;
        })->map(function ($permissions, $key) {
            return Fieldset::make($key)
                ->label(Str::headline($key))
                ->columns(1)
                ->extraAttributes([
                    'class' => 'h-full',
                ])
                ->hidden(fn() => $permissions->filter(fn($permission) => auth()->user()->can('assign', $permission))->isEmpty())
                ->columnSpan([
                    'lg' => 2,
                    'xl' => 1,
                    // 'xl' => fn($component) => count($component->getChildComponents()) > 5 ? 2 : 1,
                ])
                ->schema($permissions->map(function ($permission) {
                    [$context, $action] = explode(':', $permission->name);
                    return Checkbox::make('permissions.'.$permission->name)
                        ->hidden(fn() => !auth()->user()->can('assign', $permission))
                        ->label(Str::headline($action));
                })->toArray());
        })->toArray();

        return $permissionsGroupedByEntity;
    }
}
