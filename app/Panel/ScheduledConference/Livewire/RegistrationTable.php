<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Models\Registration;
use App\Panel\ScheduledConference\Pages\RegistrationDetail;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Squire\Models\Currency;

class RegistrationTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function mount() {}

    public function render()
    {
        return view('tables.table');
    }

    public function getTableQuery(): Builder
    {
        return Registration::query();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->recordUrl(fn($record) => RegistrationDetail::getUrl(['record' => $record]))
            ->columns([
                IndexColumn::make('No'),
                TextColumn::make('full_name')
                    ->grow(false),
                TextColumn::make('email')
                    ->grow(false),
                TextColumn::make('type'),
                TextColumn::make('cost')
                    ->getStateUsing(fn(Registration $record) => money($record->cost, $record->currency, true)->formatWithoutZeroes())
                    ->description(fn(Registration $record) => Currency::find($record->currency)?->name),
            ])
            ->headerActions([])
            ->actions([]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }
}
