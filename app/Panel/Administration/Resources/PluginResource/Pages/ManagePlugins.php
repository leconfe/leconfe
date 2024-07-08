<?php

namespace App\Panel\Administration\Resources\PluginResource\Pages;

use App\Facades\Plugin;
use App\Models\Plugin as ModelsPlugin;
use App\Panel\Administration\Resources\PluginResource;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;

class ManagePlugins extends ManageRecords
{
    protected static string $resource = PluginResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make(__('translation.plugin.tabsNameAll')),
            'disabled' => Tab::make(__('translation.plugin.tabsNameDisabled'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('enabled', false)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('add-plugin')
                ->label(__('translation.plugin.labelAddnew'))
                ->modalHeading(__('translation.plugin.modalheadingAddNewPlugin'))
                ->disabled(fn () => ! auth()->user()->can('install', ModelsPlugin::class))
                ->form([
                    FileUpload::make('file')
                        ->disk('plugins-tmp')
                        ->acceptedFileTypes(['application/zip'])
                        ->required(),
                ])
                ->action(function (array $data) {

                    try {
                        Plugin::install(Plugin::getTempDisk()->path($data['file']));
                    } catch (\Throwable $th) {
                        Notification::make('install-failed')
                            ->danger()
                            ->title(__('translation.plugin.titleInstallFailed'))
                            ->body($th->getMessage())
                            ->send();

                        return;
                    } finally {
                        Plugin::getTempDisk()->delete($data['file']);
                    }

                    Notification::make('install-success')
                        ->title(__('translation.plugin.titleInstallSuccess'))
                        ->success()
                        ->body(__('translation.plugin.titlePluginInstalledSuccessfully'))
                        ->send();
                })
                ->modalSubmitActionLabel(__('translation.button.submit')),
        ];
    }
}
