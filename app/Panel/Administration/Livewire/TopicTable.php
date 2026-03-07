<?php

namespace App\Panel\Administration\Livewire;

use App\Models\Topic;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class TopicTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    protected function getEloquentQuery(): Builder
    {
        return Topic::query()->websiteTopics();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getEloquentQuery())
            ->heading(__('general.topics'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('general.create'))
                    ->form([
                        TextInput::make('name')->label(__('general.name'))->required(),
                    ])
                    ->using(function (array $data) {
                        $data['conference_id'] = 0;
                        $topic = Topic::create($data);
                        $topic->setMeta('type', 'website');
                        return $topic;
                    }),
            ])
            ->columns([
                TextColumn::make('name')->label(__('general.name'))->searchable()->wrap(),
            ])
            ->actions([
                DeleteAction::make()
                    ->label(__('general.delete')),
                EditAction::make()
                    ->label(__('general.edit'))
                    ->form([
                        TextInput::make('name')->label(__('general.name'))->required(),
                    ])
                    ->using(function (Topic $record, array $data) {
                        $record->update($data);
                    }),
            ]);
    }
}
