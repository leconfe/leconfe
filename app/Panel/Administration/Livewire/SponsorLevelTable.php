<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Stakeholders\StakeholderLevelCreateAction;
use App\Actions\Stakeholders\StakeholderLevelUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Models\StakeholderLevel;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
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
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class SponsorLevelTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(StakeholderLevel::sponsors()->with('translate'))
            ->heading(__('general.sponsorship_levels'))
            ->reorderable('order_column')
            ->defaultSort('order_column', 'asc')
            ->columns([
                IndexColumn::make('no.'),
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->formatStateUsing(fn (StakeholderLevel $record) => $record->translated('name'))
                    ->description(fn (StakeholderLevel $record) => $record->translated('description'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereTranslatableLike('name', $search)),
                ToggleColumn::make('is_shown')
                    ->label(__('general.shown')),
            ])

            ->headerActions([
                CreateAction::make()
                    ->label(__('general.add_sponsorship_level'))
                    ->modalHeading(__('general.create_sponsorship_level'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(fn (array $data) => StakeholderLevelCreateAction::run($data)),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->mutateRecordDataUsing(fn (StakeholderLevel $record, array $data) => $record->fillTranslatableFormState($data, ['name', 'description']))
                    ->action(fn (StakeholderLevel $record, array $data) => StakeholderLevelUpdateAction::run($record, $data)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(StakeholderLevel::class)
            ->schema([
                Hidden::make('type')
                    ->default(StakeholderLevel::TYPE_SPONSOR),
                MultilanguageComponent::make([
                    TextInput::make('name')
                        ->label(__('general.name'))
                        ->required(),
                    Textarea::make('description')
                        ->label(__('general.description')),
                ]),
            ]);
    }
}
