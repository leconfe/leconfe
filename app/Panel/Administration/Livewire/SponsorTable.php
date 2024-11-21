<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Stakeholders\StakeholderCreateAction;
use App\Actions\Stakeholders\StakeholderUpdateAction;
use App\Models\Stakeholder;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;

class SponsorTable extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public function render()
    {
        return view('tables.table');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Stakeholder::sponsors())
            ->heading(__('general.sponsors'))
            ->reorderable('order_column')
            ->defaultSort('order_column', 'asc')
            ->defaultGroup('level.name')
            ->columns([
                IndexColumn::make('no.'),
                SpatieMediaLibraryImageColumn::make('logo')
                    ->label(__('general.logo'))
                    ->collection('logo')
                    ->collection('logo'),
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->description(fn (Stakeholder $record) => $record->description)
                    ->searchable(),
                TextColumn::make('level.name')
                    ->label(__('general.level'))
                    ->badge()
                    ->searchable(),
                ToggleColumn::make('is_shown')
                    ->label(__('general.shown')),
            ])
            ->emptyStateHeading(__('general.no_sponsors'))
            ->emptyStateDescription(__('general.add_sponsor_to_get_started'))
            ->headerActions([
                CreateAction::make()
                    ->label(__('general.add_sponsor'))
                    ->modalHeading(__('general.create_sponsor'))
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['type'] = Stakeholder::TYPE_SPONSOR;

                        return $data;
                    })
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(fn (array $data) => StakeholderCreateAction::run($data)),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->mutateRecordDataUsing(function (Stakeholder $record, array $data): array {
                        $data['meta']['url'] = $record->getMeta('url');

                        return $data;
                    })
                    ->mutateFormDataUsing(function (Stakeholder $record, array $data): array {
                        $data['type'] = Stakeholder::TYPE_SPONSOR;

                        return $data;
                    })
                    ->form(fn (Form $form) => $this->form($form))
                    ->using(fn (Stakeholder $record, array $data) => StakeholderUpdateAction::run($record, $data)),
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
                SpatieMediaLibraryFileUpload::make('logo')
                    ->label(__('general.logo'))
                    ->image()
                    ->key('logo')
                    ->collection('logo')
                    ->alignCenter()
                    ->imageResizeUpscale(false),
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required(),
                TextInput::make('meta.url')
                    ->label(__('general.url'))
                    ->url()
                    ->validationMessages([
                        'url' => __('general.url_must_be_valid'),
                    ]),
                Select::make('level_id')
                    ->label(__('general.level'))
                    ->relationship('level', 'name', fn ($query) => $query->sponsors()->orderBy('order_column', 'asc')),
            ]);
    }
}
