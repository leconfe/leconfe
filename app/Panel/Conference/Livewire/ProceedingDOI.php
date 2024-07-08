<?php

namespace App\Panel\Conference\Livewire;

use App\Classes\DOIGenerator;
use App\Models\Enums\DOIStatus;
use App\Models\Proceeding;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\MaxWidth;
use Filament\Support\View\Components\Modal;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Livewire\Component;
use Filament\Tables\Table;

class ProceedingDOI extends Component implements HasForms, HasTable
{
    use InteractsWithTable;
    use InteractsWithForms;

    public function mount()
    {
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Proceeding::query()->with('doi'))
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->label(__('translation.subsmissionDOI.subsmissionDOILabelTitle'))
                    ->searchable(),
                TextColumn::make('doi.doi')
                    ->searchable()
                    ->label('DOI'),
                TextColumn::make('doi.status')
                    ->badge()
                    ->label(__('translation.subsmissionDOI.subsmissionDOILabelStatus')),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DOIStatus::options())
                    ->attribute('doi.status')
                    ->modifyQueryUsing(function ($data, $query) {
                        return !$data['value'] ? $query : $query->whereHas('doi', fn ($query) => $query->where('status', $data['value']));
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label(__('translation.button.edit'))
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->fillForm(function (Proceeding $record, Table $table) {
                        return [
                            'doi' => $record->doi?->doi,
                        ];
                    })
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->modalHeading(fn ($record) => $record->title)
                    ->form([
                        TextInput::make('doi')
                            ->label('DOI')
                            ->suffixAction(
                                FormAction::make('generate')
                                    ->label(__('translation.subsmissionDOI.subsmissionDOILabelGenerate'))
                                    ->button()
                                    // ->outlined()
                                    // ->color('secondary')
                                    ->action(fn (Set $set) => $set('doi', DOIGenerator::generate()))
                            ),
                    ])
                    ->action(fn (Proceeding $record, array $data) => $record->doi()->updateOrCreate(['id' => $record->doi?->id], ['doi' => $data['doi']]))
            ])
            ->bulkActions([
                // ...
            ]);
    }
    public function render()
    {
        return view('panel.conference.livewire.proceeding-doi');
    }
}
