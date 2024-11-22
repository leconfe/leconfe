<?php

namespace App\Panel\Administration\Livewire;

use App\Models\PluginGallery;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;
use Livewire\Component;

class PluginGalleryTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->queryStringIdentifier('gallery')
            ->query(PluginGallery::query())
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (PluginGallery $record) => new HtmlString($record->summary))
                    ->color('primary')
                    ->wrap()
                    ->weight(FontWeight::Medium)
                    ->action(
                        Action::make('details')
                            ->modal()
                            ->modalHeading(fn ($record) => $record->name)
                            ->registerModalActions([
                                Action::make('install')
                                    ->extraAttributes([
                                        'class' => 'w-full',
                                    ])
                                    ->authorize(fn (PluginGallery $record) => auth()->user()->can('install', $record))
                                    ->visible(fn (PluginGallery $record) => auth()->user()->can('install', $record))
                                    ->action(fn (PluginGallery $record) => $this->install($record))
                                    ->cancelParentActions('details'),
                                Action::make('upgrade')
                                    ->authorize(fn (PluginGallery $record) => auth()->user()->can('install', $record))
                                    ->visible(fn (PluginGallery $record) => auth()->user()->can('install', $record))
                                    ->extraAttributes([
                                        'class' => 'w-full',
                                    ])
                                    ->color('success')
                                    ->action(fn (PluginGallery $record) => $this->install($record))
                                    ->cancelParentActions('details'),
                            ])
                            ->modalSubmitAction(false)
                            ->modalCancelAction(false)
                            ->modalContent(fn (PluginGallery $record, Action $action): View => view('tables.actions.plugin-gallery-details', ['record' => $record, 'action' => $action]))
                    ),
                TextColumn::make('version')
                    ->label(__('general.version'))
                    ->getStateUsing(fn (PluginGallery $record) => $record->getLatestCompatibleRelease()?->get('version')),
                TextColumn::make('author')
                    ->searchable(),
                TextColumn::make('status')
                    ->getStateUsing(function (PluginGallery $record) {
                        if (! $record->isInstalled()) {
                            return 'Not Installed';
                        }

                        if ($record->isUpgradable()) {
                            return 'Upgradable';
                        }

                        return 'Installed';
                    })
                    ->badge()
                    ->color(function (PluginGallery $record) {
                        if (! $record->isInstalled()) {
                            return 'gray';
                        }

                        if ($record->isUpgradable()) {
                            return 'success';
                        }

                        return 'primary';
                    }),
            ])
            ->filters([])
            ->emptyStateActions([]);
    }

    public function install(PluginGallery $record)
    {
        $message = $record->isUpgradable() ? 'Upgrade' : 'Install';

        $process = $record->install();

        $notification = Notification::make();

        if ($process) {
            $notification->success()->title("{$message} Success");
        } else {
            $notification->danger()->title("{$message} Failed");
        }

        $notification->send();

        $this->dispatch('refresh-table')->to(PluginTable::class);
    }

    #[On('refresh-table')]
    public function refreshTable()
    {
        $this->resetPage();
    }
}
