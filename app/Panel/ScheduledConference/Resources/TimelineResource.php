<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Facades\Setting;
use App\Models\Timeline;
use App\Panel\ScheduledConference\Resources\TimelineResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class TimelineResource extends Resource
{
    protected static ?string $model = Timeline::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function getNavigationGroup(): string
    {
        return __('general.conference');
    }

    public static function getModelLabel(): string
    {
        return __('general.timeline');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('general.name'))
                    ->required(),
                Textarea::make('description')
                    ->label(__('general.description'))
                    ->maxLength(255),
                DatePicker::make('date')
                    ->label(__('general.date'))
                    ->required(),
                Select::make('type')
                    ->label(__('general.type'))
                    ->options(Timeline::getTypes())
                    ->helperText(__('general.type_integrates_with_workflow_process'))
                    ->unique(
                        ignorable: fn () => $form->getRecord(),
                        modifyRuleUsing: fn (Unique $rule) => $rule->where('scheduled_conference_id', app()->getCurrentScheduledConferenceId()),
                    )
                    ->native(false),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(Timeline::query())
            ->heading(__('general.timeline'))
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')
                    ->label(__('general.date'))
                    ->dateTime(Setting::get('format_date'))
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('general.name')),
                ToggleColumn::make('hide')
                    ->label(__('general.hidden')),
            ])
            ->filters([
                // ...
            ])
            ->actions([
                EditAction::make()
                    ->modalWidth(MaxWidth::ExtraLarge),
                ActionGroup::make([
                    DeleteAction::make(),
                ]),
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
            'index' => Pages\ManageTimeline::route('/'),
        ];
    }
}
