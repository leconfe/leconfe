<?php

namespace App\Panel\Series\Resources;

use App\Models\Venue;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Infolists\Components\TextEntry;
use App\Panel\Series\Resources\VenueResource\Pages;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

class VenueResource extends Resource
{

    protected static ?string $model = Venue::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    public static function getNavigationLabel(): string
    {
        return __('translation.venueResource.getModelLabelVenue');
    }

    public static function getModelLabel(): string
    {
        return __('translation.venueResource.getModelLabelVenue');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('translation.venueResource.venueResourceLabelName'))
                            ->required(),
                        TextInput::make('location')
                            ->label(__('translation.venueResource.venueResourceLabelLocation'))
                            ->required(),
                        SpatieMediaLibraryFileUpload::make('photo')
                            ->label(__('translation.venueResource.venueResourceLabelPhoto'))
                            ->collection('thumbnail')
                            ->conversion('thumb')
                            ->multiple(false)
                            ->required(),
                        Textarea::make('description')
                            ->label(__('translation.venueResource.venueResourceLabelDescription')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('translation.venueResource.venueResourceLabelName'))
                    ->searchable(),
                TextColumn::make('location')
                    ->label(__('translation.venueResource.venueResourceLabelLocation')),
                SpatieMediaLibraryImageColumn::make('photo')
                    ->label(__('translation.venueResource.venueResourceLabelPhoto'))
                    ->collection('thumbnail')
                    ->conversion('thumb'),
            ])
            ->actions([
                ViewAction::make()
                    ->infolist([
                        SpatieMediaLibraryImageEntry::make('photo')
                            ->collection('thumbnail')
                            ->conversion('thumb')
                            ->label(__('translation.venueResource.venueResourceLabelPhoto'))
                            ->visible(fn ($record) => $record->hasMedia('thumbnail')),
                        TextEntry::make('name')
                            ->size(TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->label(__('translation.venueResource.venueResourceLabelName'))
                            ->color('secondary'),
                        TextEntry::make('location')
                            ->label(__('translation.venueResource.venueResourceLabelLocation'))
                            ->color('secondary')
                            ->icon('heroicon-m-map-pin'),
                        TextEntry::make('description')
                            ->label(__('translation.venueResource.venueResourceLabelDescription'))
                            ->color('secondary'),
                    ]),
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth('2xl')
                        ->form(fn ($form) => static::form($form)),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageVenues::route('/'),
        ];
    }
}
