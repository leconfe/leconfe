<?php

namespace App\Panel\Conference\Livewire;

use App\Classes\DOIGenerator;
use App\Models\Enums\DOIStatus;
use App\Models\Proceeding;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Set;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ProceedingDOI extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function mount() {}

    public function table(Table $table): Table
    {
        return $table
            ->query(Proceeding::query()->with(['doi', 'translate']))
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->formatStateUsing(fn (Proceeding $record) => $record->translated('title'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereTranslatableLike('title', $search)),
                TextColumn::make('doi.doi')
                    ->searchable()
                    ->label('DOI'),
                TextColumn::make('doi.status')
                    ->badge()
                    ->label('Status'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DOIStatus::options())
                    ->attribute('doi.status')
                    ->modifyQueryUsing(function ($data, $query) {
                        return ! $data['value'] ? $query : $query->whereHas('doi', fn ($query) => $query->where('status', $data['value']));
                    }),
            ])
            ->actions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->button()
                    ->fillForm(function (Proceeding $record, Table $table) {
                        return [
                            'doi' => $record->doi?->doi,
                        ];
                    })
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->modalHeading(fn (Proceeding $record) => $record->translated('title'))
                    ->form([
                        TextInput::make('doi')
                            ->label('DOI')
                            ->suffixAction(
                                FormAction::make('generate')
                                    ->label('Generate')
                                    ->button()
                                    // ->outlined()
                                    // ->color('secondary')
                                    ->action(fn (Set $set) => $set('doi', DOIGenerator::generate()))
                            ),
                    ])
                    ->action(fn (Proceeding $record, array $data) => $record->doi()->updateOrCreate(['id' => $record->doi?->id], ['doi' => $data['doi']])),
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
