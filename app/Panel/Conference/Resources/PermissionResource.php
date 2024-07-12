<?php

namespace App\Panel\Conference\Resources;

use App\Models\Permission;
use App\Panel\Conference\Resources\PermissionResource\Pages;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    public static function getModelLabel(): string
    {
        return __('translation.permissionResource.getModelLabel');
    }



    public static function getNavigationLabel(): string
    {
        return __('translation.permissionResource.getModelLabel');
    }



    protected static ?int $navigationSort = 7;

    /**
     * This Resource is only for development purposes.
     */
    public static function isDiscovered(): bool
    {
        return ! app()->isProduction();
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('context')
                    ->label(__('translation.permissionResource.labelContext'))
                    ->datalist(fn () => static::getEloquentQuery()->pluck('context')->unique()->sort()->values()->all())
                    ->dehydrateStateUsing(fn (string $state): string => Str::studly($state))
                    ->helperText(__('translation.permissionResource.helperTextContext')),
                TextInput::make('action')
                    ->label(__('translation.permissionResource.labelAction'))
                    ->datalist(fn () => static::getEloquentQuery()->pluck('action')->unique()->sort()->values()->all())
                    ->helperText(__('translation.permissionResource.helperTextActionMustBeCamelCase'))
                    ->dehydrateStateUsing(fn (string $state): string => Str::camel($state)),
                CheckboxList::make('roles')
                    ->label(__('translation.permissionResource.labelRoles'))
                    ->relationship('roles', 'name')
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->deferLoading()
            ->columns([
                TextColumn::make('name')
                    ->label(__('translation.permissionResource.labelName'))
                    ->sortable()
                    ->badge()
                    ->searchable(),
                TextColumn::make('context')
                    ->label(__('translation.permissionResource.labelContext'))
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state))
                    ->searchable(),
                TextColumn::make('action')
                    ->label(__('translation.permissionResource.labelAction'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn (string $state): string => Str::headline($state)),
                TextColumn::make('roles_count')
                    ->label(__('translation.permissionResource.labelAssignedRoles'))
                    ->counts('roles')
                    ->badge()
                    ->color(fn (int $state) => $state > 0 ? 'primary' : 'gray'),
            ])
            ->groups([
                Group::make('context'),
                Group::make('action')
                    ->getTitleFromRecordUsing(fn (Permission $permission): string => Str::headline($permission->action)),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->using(function (Permission $record, Tables\Actions\DeleteAction $action) {
                        try {
                            return $record->delete();
                        } catch (\Throwable $th) {
                            $action->failureNotificationTitle($th->getMessage());
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePermissions::route('/'),
        ];
    }
}
