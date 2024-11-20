<?php

namespace App\Panel\Conference\Resources;

use App\Facades\Setting;
use App\Forms\Components\TinyEditor;
use App\Models\Proceeding;
use App\Panel\Conference\Resources\ProceedingResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Stevebauman\Purify\Facades\Purify;

class ProceedingResource extends Resource
{
    protected static ?string $model = Proceeding::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function getNavigationLabel(): string
    {
        return __('general.proceeding');
    }

    public static function getModelLabel(): string
    {
        return __('general.proceeding');
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
                Fieldset::make('Identification')
                    ->label(__('general.identification'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('volume')
                            ->label(__('general.volume'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('number')
                            ->label(__('general.number'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('year')
                            ->label(__('general.year'))
                            ->numeric()
                            ->minValue(0),
                    ]),
                TextInput::make('title')
                    ->label(__('general.title'))
                    ->required(),
                TinyEditor::make('description')
                    ->label(__('general.description'))
                    ->profile('basic')
                    ->minHeight(300)
                    ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                SpatieMediaLibraryFileUpload::make('cover')
                    ->label(__('general.cover'))
                    ->collection('cover')
                    ->imageResizeUpscale(false)
                    ->image()
                    ->conversion('thumb'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->label(__('general.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('submissions_count')
                    ->label(__('general.submissions'))
                    ->counts('submissions'),
                TextColumn::make('current')
                    ->label(__('general.current'))
                    ->hidden(fn (Component $livewire) => $livewire->activeTab === 'future')
                    ->state(fn (Proceeding $record) => $record->published && $record->current ? __('general.current') : '')
                    ->badge(),
                TextColumn::make('published_at')
                    ->label(__('general.published_at'))
                    ->sortable()
                    ->hidden(fn (Component $livewire) => $livewire->activeTab === 'future')
                    ->date(Setting::get('format_date')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make()
                        ->modalWidth('xl'),
                    Tables\Actions\Action::make('preview')
                        ->label(__('general.preview'))
                        ->icon('heroicon-o-eye')
                        ->hidden(fn (Proceeding $record) => ! $record->published)
                        ->url(fn (Proceeding $record) => route('livewirePageGroup.conference.pages.proceeding-detail', [$record->id]), true),
                    Tables\Actions\Action::make('publish')
                        ->label(__('general.publish'))
                        ->requiresConfirmation()
                        ->color('success')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->hidden(fn (Proceeding $record) => $record->published)
                        ->action(fn (Proceeding $record) => $record->publish()),
                    Tables\Actions\Action::make('unpublish')
                        ->label(__('general.unpublish'))
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->hidden(fn (Proceeding $record) => ! $record->published)
                        ->action(fn (Proceeding $record) => $record->unpublish()),
                    Tables\Actions\Action::make('set_as_current')
                        ->label(__('general.set_as_current'))
                        ->requiresConfirmation()
                        ->icon('heroicon-s-arrow-up-circle')
                        ->visible(fn (Proceeding $record) => $record->published && ! $record->current)
                        ->action(fn (Proceeding $record) => $record->setAsCurrent()),
                    Tables\Actions\DeleteAction::make()
                        ->using(function (Proceeding $record, Tables\Actions\DeleteAction $action) {
                            try {
                                $record->delete();
                            } catch (\Throwable $th) {
                                $action->failureNotificationTitle($th->getMessage());

                                return false;
                            }
                        }),
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
