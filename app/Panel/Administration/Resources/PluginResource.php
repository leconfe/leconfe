<?php

namespace App\Panel\Administration\Resources;

use App\Facades\Plugin as FacadesPlugin;
use App\Models\Plugin;
use App\Panel\Administration\Resources\PluginResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PluginResource extends Resource
{
    protected static ?string $model = Plugin::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    // protected static ?string $navigationGroup = 'Settings';

    public static function getNavigationGroup(): string
    {
        return __('translation.pluginResource.navigationGroupTitle');
    }

    public static function getModelLabel(): string
    {
        return __('translation.pluginResource.pluginResourcegetNavigationLabels');
    }



    public static function getNavigationLabel(): string
    {
        return __('translation.pluginResource.pluginResourcegetNavigationLabels');
    }


    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->label(__('translation.pluginResource.pluginResourceLabelTitle'))
                    ->wrap()
                    ->sortable()
                    ->searchable()
                    ->description(fn (Plugin $record) => $record->description)
                    ->weight(fn (Plugin $record) => FacadesPlugin::getSetting($record->id, 'enabled') ? FontWeight::SemiBold : FontWeight::Light)
                    ->url(fn (Plugin $record) => FacadesPlugin::getSetting($record->id, 'enabled') ? FacadesPlugin::getPlugin($record->id)?->getPluginPage() : null)
                    ->color(fn (Plugin $record) => (FacadesPlugin::getSetting($record->id, 'enabled') && FacadesPlugin::getPlugin($record->id)?->getPluginPage()) ? 'primary' : null),
                TextColumn::make('author')
                    ->label(__('translation.pluginResource.pluginResourceLabelAuthor')),
                ToggleColumn::make('enabled')
                    ->label(__('translation.pluginResource.pluginResourceLabelEnabled'))
                    ->getStateUsing(fn (Plugin $record) => FacadesPlugin::getSetting($record->id, 'enabled'))
                    ->updateStateUsing(function (Plugin $record, $state) {
                        FacadesPlugin::enable($record->id, $state);

                        $record->enabled = $state;
                        if ($state) {
                            FacadesPlugin::bootPlugin($record->path);
                        }

                        return $state;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\DeleteAction::make()
                        ->action(function (Plugin $record) {
                            FacadesPlugin::uninstall($record->id);
                        }),
                ]),
                // TODO : Add actions based on plugin. Currently there's no way to create a dinamically action

            ])
            ->emptyStateActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePlugins::route('/'),
        ];
    }
}
