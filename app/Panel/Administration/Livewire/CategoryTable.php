<?php

namespace App\Panel\Administration\Livewire;

use App\Models\ScheduledConferenceCategory;
use App\Models\Site;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\Attributes\On;

class CategoryTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    protected function getEloquentQuery(): Builder
    {
        return ScheduledConferenceCategory::query();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getEloquentQuery())
            ->heading(__('general.categories'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.category'))
                    ->getStateUsing(fn($record) => $record->name ?? '')
                    ->searchable()
                    ->wrap(),
            ])
            ->headerActions([
                TableAction::make('createCategory')
                    ->label(__('general.create'))
                    ->form([
                        TextInput::make('name')->label(__('general.category'))->required(),
                    ])
                    ->action(function (array $data) {
                        $site = Site::getSite();
                        $rows = $site->getMeta('scheduled_conference_categories', []);

                        $maxId = 0;
                        foreach ($rows as $r) {
                            if (isset($r['id']) && is_numeric($r['id'])) {
                                $maxId = max($maxId, (int) $r['id']);
                            }
                        }

                        $rows[] = [
                            'id' => $maxId + 1,
                            'name' => $data['name'],
                        ];

                        $site->setMeta('scheduled_conference_categories', $rows);

                        $this->dispatch('refresh-table')->to(CategoryTable::class);
                    })
                    ->successNotificationTitle(__('general.saved_successfuly')),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('general.edit'))
                    ->form([
                        TextInput::make('name')->label(__('general.category'))->required(),
                    ])
                    ->fillForm(fn(ScheduledConferenceCategory $record) => ['name' => $record->name ?? ''])
                    ->action(function (ScheduledConferenceCategory $record, array $data) {
                        $site = Site::getSite();
                        $rows = $site->getMeta('scheduled_conference_categories', []);

                        foreach ($rows as &$r) {
                            if (isset($r['id']) && (string) $r['id'] === (string) $record->getKey()) {
                                $r['name'] = $data['name'];
                                break;
                            }
                        }

                        $site->setMeta('scheduled_conference_categories', $rows);

                        $this->dispatch('refresh-table')->to(CategoryTable::class);
                    })
                    ->successNotificationTitle(__('general.saved_successfuly')),
                DeleteAction::make()
                    ->label(__('general.delete'))
                    ->action(function (ScheduledConferenceCategory $record) {
                        $site = Site::getSite();
                        $rows = $site->getMeta('scheduled_conference_categories', []);

                        $rows = array_values(array_filter($rows, function ($r) use ($record) {
                            return (string) ($r['id'] ?? '') !== (string) $record->getKey();
                        }));

                        $site->setMeta('scheduled_conference_categories', $rows);

                        $this->dispatch('refresh-table')->to(CategoryTable::class);
                    }),
            ]);
    }

    #[On('refresh-table')]
    public function refreshTable(): void
    {
        $this->resetPage();
    }
}
