<?php

namespace App\Panel\Series\Livewire;

use App\Models\Sponsor;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class   SponsorSetting extends Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    private static function sponsorFormSchema()
    {
        return [
            TextInput::make('name')
                ->label(__('translation.serieSetting.sponsorSettingLabelName'))
                ->required(),
            SpatieMediaLibraryFileUpload::make('logo')
                ->multiple(false)
                ->collection('logo')
                ->image()
        ];
    }

    public function table(Table $table)
    {
        return $table
            ->heading(__('translation.serieSetting.sponsorSettingHeadingSponsors'))
            ->reorderable('order_column')
            ->headerActions([
                CreateAction::make('create')
                    ->label(__('translation.serieSetting.sponsorSettingLabelAddSponsor'))
                    ->modalWidth('xl')
                    ->model(Sponsor::class)
                    ->form(static::sponsorFormSchema())
            ])
            ->query(fn (): Builder => Sponsor::ordered()->with('media'))
            ->emptyStateHeading(__('translation.serieSetting.sponsorSettingEmptyStateHeading'))
            ->columns([
                Split::make([
                    SpatieMediaLibraryImageColumn::make('logo')
                        ->collection('logo')
                        ->conversion('small')
                        ->height('3rem')
                        ->grow(false),
                    Stack::make([
                        TextColumn::make('name')
                    ])
                ])
            ])
            ->actions([
                EditAction::make()
                    ->form(static::sponsorFormSchema()),
                DeleteAction::make()
            ])
            ->filters([
                //
            ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.table');
    }
}
