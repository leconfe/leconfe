<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\SubmissionFiles\SubmissionFileTypeCreateAction;
use App\Actions\SubmissionFiles\SubmissionFileTypeUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\SubmissionFileType;
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
                    ->getStateUsing(fn (SubmissionFileType $record) => $record->getLocalizedMeta('name')) // mungkin kalo ditambahkan ini fungsi yang dibuat sebelumnya itu akan null/kosong, karena sekarang fungsinya mengambil name dari LocalizedMeta.
                    ->searchable(),
                TextColumn::make('files_count')
                    ->label(__('general.files')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('general.add_a_component'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(fn (array $data) => SubmissionFileTypeCreateAction::run($data)),
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->mutateRecordDataUsing(function (array $data, SubmissionFileType $record) {
                        $data['meta'] = $record->getAllMeta();

                        return $data;
                    })
                    ->action(fn (SubmissionFileType $record, array $data) => SubmissionFileTypeUpdateAction::run($record, $data)),
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
                MultilanguageComponent::make([
                    TextInput::make('meta.name')
                    ->label(__('general.name'))
                    ->required(),
                ]),
                
            ]);
    }
}
