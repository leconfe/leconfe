<?php

namespace App\Panel\Conference\Resources\Conferences;

use App\Models\Topic;
use App\Panel\Conference\Resources\Conferences\TopicResource\Pages;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TopicResource extends Resource
{
    // protected static ?string $navigationGroup = 'Conferences';

    public static function getModelLabel(): string
    {
        return __('translation.topicResource.topicResourceGetModelLabel');
    }

    public static function getNavigationGroup(): string
    {
        return __('translation.announcementResource.announcementResourceNavigationGroup');
    }

    protected static ?string $model = Topic::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('translation.topicResource.topicResourceLabelName'))
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                ->label(__('translation.topicResource.topicResourceLabelName')),
            ])
            ->actions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth('2xl'),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageTopics::route('/'),
        ];
    }
}
