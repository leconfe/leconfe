<?php

namespace App\Panel\Administration\Livewire;

use App\Facades\Plugin as FacadesPlugin;
use App\Models\Plugin;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Attributes\On;
use Livewire\Component;

class PluginTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    #[On('refresh-table')]
    public function refreshTable()
    {
        $this->resetPage();
    }

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('plugin')
            ->query(Plugin::query()->hidden(false))
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->wrap()
                    ->sortable()
                    ->searchable()
                    ->description(fn (Plugin $record) => $record->description)
                    ->weight(fn (Plugin $record) => $record->plugin->isEnabled() ? FontWeight::SemiBold : FontWeight::Light)
                    ->url(fn (Plugin $record) => $record->plugin->isEnabled() ? $record->plugin?->getPluginPage() : null)
                    ->color(fn (Plugin $record) => ($record->plugin->isEnabled() && $record->plugin?->getPluginPage()) ? 'primary' : null),
                TextColumn::make('version')
                    ->label(__('general.version')),
                TextColumn::make('author')
                    ->label(__('general.author')),
                ToggleColumn::make('enabled')
                    ->label(__('general.enabled'))
                    ->visible(auth()->user()->can('Plugin:update'))
                    ->getStateUsing(fn (Plugin $record) => $record->plugin->isEnabled())
                    ->updateStateUsing(function (Plugin $record, $state) {
                        $record->plugin->enable($state);

                        return $state;
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->authorize(fn (Plugin $record) => auth()->user()->can('delete', $record))
                        ->action(function (Plugin $record, $action) {
                            FacadesPlugin::uninstall($record->id);

                            $this->dispatch('refresh-table')->to(PluginGalleryTable::class);

                            $action->success();
                        })
                        ->successNotificationTitle(fn (Plugin $record) => $record->name.' uninstalled.'),
                ]),
                // TODO : Add actions based on plugin. Currently there's no way to create a dinamically action

            ])
            ->filters([
                SelectFilter::make('enabled')
                    ->label(__('general.enabled'))
                    ->options([
                        '1' => __('general.enabled'),
                        '0' => __('general.disabled'),
                    ]),
            ])
            ->emptyStateActions([]);
    }
}
