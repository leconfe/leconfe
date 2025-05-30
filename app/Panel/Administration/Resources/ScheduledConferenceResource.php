<?php

namespace App\Panel\Administration\Resources;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Facades\Setting;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\ScheduledConference;
use App\Panel\Conference\Resources\ScheduledConferenceResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScheduledConferenceResource extends Resource
{
    protected static ?string $model = ScheduledConference::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function getNavigationLabel(): string
    {
        return __('general.scheduled_conference');
    }

    public static function getModelLabel(): string
    {
        return __('general.scheduled_conference');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                TextInput::make('title')
                    ->label(__('general.title'))
                    ->autofocus()
                    ->autocomplete()
                    ->required()
                    ->placeholder(__('general.enter_the_title_of_the_serie')),
                TextInput::make('path')
                    // ->prefix(fn () => route('livewirePageGroup.conference.pages.home', ['conference' => app()->getCurrentConference()->path]).'/scheduled/')
                    ->label(__('general.path'))
                    ->rule('alpha_dash')
                    ->required(),
                Grid::make()
                    ->schema([
                        DatePicker::make('date_start')
                            ->label(__('general.start_date'))
                            ->placeholder(__('general.enter_the_start_date_of_the_serie'))
                            ->requiredWith('date_end'),
                        DatePicker::make('date_end')
                            ->label(__('general.end_date'))
                            ->afterOrEqual('date_start')
                            ->requiredWith('date_start')
                            ->placeholder(__('general.enter_the_end_date_of_the_serie')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->recordUrl(fn (ScheduledConference $record) => route('filament.scheduledConference.pages.dashboard', ['serie' => $record]))
            ->modifyQueryUsing(fn (Builder $query) => $query->latest())
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->label(__('general.title'))
                    ->searchable()
                    ->description(fn (ScheduledConference $record) => $record->current ? 'Current' : null)
                    ->sortable()
                    ->wrap()
                    ->wrapHeader(),
                TextColumn::make('date_start')
                    ->label(__('general.start_date'))
                    ->date(Setting::get('format_date')),
                TextColumn::make('date_end')
                    ->label(__('general.end_date'))
                    ->date(Setting::get('format_date')),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('publish')
                        ->label(__('general.publish'))
                        ->requiresConfirmation()
                        ->icon('heroicon-o-arrow-up-on-square')
                        ->color('primary')
                        ->form([
                            Checkbox::make('set_as_current')
                                ->label(__('general.set_as_current')),
                        ])
                        ->hidden(fn (ScheduledConference $record) => $record->isArchived() || $record->isCurrent() || $record->isPublished() || $record->trashed())
                        ->action(function (ScheduledConference $record, array $data, Tables\Actions\Action $action) {
                            $data['state'] = $data['set_as_current'] ? ScheduledConferenceState::Current : ScheduledConferenceState::Published;

                            ScheduledConferenceUpdateAction::run($record, $data);

                            return $action->success();
                        }),
                    Tables\Actions\Action::make('set_as_current')
                        ->label(__('general.set_as_current'))
                        ->requiresConfirmation()
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->hidden(fn (ScheduledConference $record) => $record->isCurrent() || $record->isDraft() || $record->trashed())
                        ->action(fn (ScheduledConference $record, Tables\Actions\Action $action) => $record->update(['state' => ScheduledConferenceState::Current]) && $action->success())
                        ->successNotificationTitle(fn (ScheduledConference $scheduledConference) => $scheduledConference->title.' is set as current'),
                    Tables\Actions\Action::make('set_as_draft')
                        ->label(__('general.set_as_draft'))
                        ->requiresConfirmation()
                        ->icon('heroicon-o-pencil-square')
                        ->hidden(fn (ScheduledConference $record) => $record->isDraft() || $record->trashed())
                        ->action(fn (ScheduledConference $record, Tables\Actions\Action $action) => $record->update(['state' => ScheduledConferenceState::Draft]) && $action->success())
                        ->successNotificationTitle(fn (ScheduledConference $scheduledConference) => $scheduledConference->title.' is set as current'),
                    Tables\Actions\Action::make('move_to_archive')
                        ->label(__('general.move_to_archive'))
                        ->requiresConfirmation()
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->color('warning')
                        ->hidden(fn (ScheduledConference $record) => $record->isArchived() || $record->isDraft() || $record->trashed())
                        ->action(fn (ScheduledConference $record, Tables\Actions\Action $action) => $record->update(['state' => ScheduledConferenceState::Archived]) && $action->success())
                        ->successNotificationTitle(fn (ScheduledConference $scheduledConference) => $scheduledConference->title.' is moved to archive'),
                    Tables\Actions\EditAction::make()
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->hidden(fn (ScheduledConference $record) => $record->isArchived() || $record->trashed())
                        ->mutateRecordDataUsing(function (ScheduledConference $record, array $data) {
                            $data['meta'] = $record->getAllMeta()->toArray();

                            return $data;
                        })
                        ->using(fn (ScheduledConference $record, array $data) => ScheduledConferenceUpdateAction::run($record, $data)),
                    Tables\Actions\DeleteAction::make()
                        ->label(__('general.move_to_trash'))
                        ->modalHeading(__('general.move_to_trash'))
                        ->hidden(fn (ScheduledConference $record) => $record->isCurrent() || $record->trashed())
                        ->successNotificationTitle(__('general.serie_moved_to_trash')),
                    Tables\Actions\ForceDeleteAction::make()
                        ->label(__('general.delete_permanently'))
                        ->hidden(fn (ScheduledConference $record) => ! $record->trashed())
                        ->successNotificationTitle(__('general.serie_deleted_permanently')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageScheduledConferences::route('/'),
        ];
    }
}
