<?php

namespace App\Panel\Administration\Resources;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Conference;
use App\Panel\Administration\Resources\ConferenceResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ConferenceResource extends Resource
{
    protected static ?string $model = Conference::class;

    public static function getNavigationLabel(): string
    {
        // This returns the label for the dashboard navigation.
        return __('general.conference');
    }

    public static function getModelLabel(): string
    {
        return __('general.conference');
    }

    protected static ?string $navigationIcon = 'heroicon-o-window';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->columnSpanFull()
                    ->required(),
                TextInput::make('path')
                    ->label(__('general.path'))
                    ->helperText(__('general.url_path'))
                    ->required()
                    ->rule('alpha_dash')
                    ->unique(ignoreRecord: true)
                    ->prefix(config('app.url').'/'),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(5)
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open-conference')
                    ->label(__('general.open_conference'))
                    ->icon('heroicon-o-link')
                    ->button()
                    ->color('gray')
                    ->url(fn (Conference $record) => route('filament.conference.pages.dashboard', $record))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->button()
                    ->mutateRecordDataUsing(function (Conference $record, array $data) {
                        $data['meta'] = $record->getAllMeta()->toArray();

                        return $data;
                    })
                    ->using(fn (Conference $record, array $data) => ConferenceUpdateAction::run($record, $data)),
                Tables\Actions\DeleteAction::make()
                    ->button(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConferences::route('/'),
            // 'create' => Pages\CreateConference::route('/create'),
            // 'edit' => Pages\EditConference::route('/{record}/edit'),
        ];
    }
}
