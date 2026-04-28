<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\Topics\TopicCreateAction;
use App\Actions\Topics\TopicUpdateAction;
use App\Models\Topic;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class TopicTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => Topic::query()->orderBy('order_column'))
            ->heading(__('general.topic'))
            ->reorderable('order_column')
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make('createtopic')
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn(Form $form) => $this->form($form))
                    ->using(fn(array $data) => TopicCreateAction::run($data)),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn(Form $form) => $this->form($form))
                    ->action(fn(Topic $record, array $data) => TopicUpdateAction::run($record, $data)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required(),
            ]);
    }
}
