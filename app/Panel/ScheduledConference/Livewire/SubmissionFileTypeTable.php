<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Models\SubmissionFileType;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class SubmissionFileTypeTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(SubmissionFileType::withCount(['files']))
            ->defaultPaginationPageOption(10)
            ->heading(__('general.paper_components'))
            ->reorderable('order_column')
            ->defaultSort('order_column')
            ->columns([
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(),
                IconColumn::make('required')
                    ->label(__('general.required'))
                    ->boolean(),
                TextColumn::make('files_count')
                    ->label(__('general.files')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('general.add_a_component'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form)),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form)),
                DeleteAction::make()
                    ->hidden(fn (SubmissionFileType $record) => $record->files_count > 0),
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
                Checkbox::make('required')
                    ->label(__('general.required_upload'))
                    ->helperText(__('general.required_upload_helper')),
            ]);
    }
}
