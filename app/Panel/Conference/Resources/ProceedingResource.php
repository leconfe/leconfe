<?php

namespace App\Panel\Conference\Resources;

use Filament\Tables;
use Livewire\Component;
use Filament\Forms\Form;
use App\Facades\Setting;
use App\Models\Proceeding;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Panel\Conference\Resources\ProceedingResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class ProceedingResource extends Resource
{
    protected static ?string $model = Proceeding::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function getNavigationLabel(): string
    {
        return __('translation.proceeding.proceedingResourceTitle');
    }
   
    public static function getModelLabel(): string
    {
        return __('translation.proceeding.proceedingResourceTitle');
    }
    
    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()
            ->orderBy('order_column')
            ->withCount('submissions');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Fieldset::make(__('translation.proceeding.proceedingResourceTitle'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('volume')
                            ->label(__('translation.proceeding.proceedingResourceLabelVolume'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('number')
                            ->label(__('translation.proceeding.proceedingResourceLabelNumber'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('year')
                             ->label(__('translation.proceeding.proceedingResourceLabelYear'))
                            ->numeric()
                            ->minValue(0),
                    ]),
                TextInput::make('title')
                    ->label(__('translation.proceeding.proceedingResourceLabelTitle'))
                    ->required(),
                Textarea::make('description')
                    ->label(__('translation.proceeding.proceedingResourceLabelDescription'))
                    ->nullable()
                    ->autosize(),
                SpatieMediaLibraryFileUpload::make('cover')
                    ->label(__('translation.proceeding.proceedingResourceLabelCover'))
                    ->collection('cover')
                    ->imageResizeUpscale(false)
                    ->image()
                    ->conversion('thumb')
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->label(__('translation.proceeding.proceedingResourceLabelTableTitle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('translation.proceeding.proceedingResourceLabelTableSubmissions'))
                    ->counts('submissions'),
                TextColumn::make('current')
                    ->hidden(fn(Component $livewire) => $livewire->activeTab === 'future')
                    ->state(fn(Proceeding $record) => $record->published && $record->current ? 'Current' : '')
                    ->label(__('translation.proceeding.proceedingResourceLabelTableCurrent'))
                    ->badge(),
                TextColumn::make('published_at')
                    ->sortable()
                    ->label(__('translation.proceeding.proceedingResourceLabelTablePublishedAt'))
                    ->hidden(fn(Component $livewire) => $livewire->activeTab === 'future')
                    ->date(Setting::get('format_date'))
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->modalWidth('xl'),
                    Tables\Actions\Action::make('preview')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Proceeding $record) => route('livewirePageGroup.conference.pages.proceeding-detail', [$record->id]), true),
                    Tables\Actions\Action::make('publish')
                        ->requiresConfirmation()
                        ->icon('heroicon-o-arrow-up-tray')
                        ->hidden(fn (Proceeding $record) => $record->published)
                        ->action(fn (Proceeding $record) => $record->publish()),
                    Tables\Actions\Action::make('unpublish')
                        ->requiresConfirmation()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->hidden(fn (Proceeding $record) => !$record->published)
                        ->action(fn (Proceeding $record) => $record->unpublish()),
                    Tables\Actions\Action::make('set_as_current')
                        ->requiresConfirmation()
                        ->icon('heroicon-s-arrow-up-circle')
                        ->visible(fn (Proceeding $record) => $record->published && !$record->current)
                        ->action(fn (Proceeding $record) => $record->setAsCurrent()),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProceedings::route('/'),
            'view' => Pages\ViewProceeding::route('/{record}'),
        ];
    }
}
