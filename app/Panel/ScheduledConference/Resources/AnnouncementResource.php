<?php

namespace App\Panel\ScheduledConference\Resources;

use App\Actions\Announcements\AnnouncementUpdateAction;
use App\Facades\Setting;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Models\Announcement;
use App\Panel\ScheduledConference\Resources\AnnouncementResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static ?string $modelLabel = 'Announcement';

    public static function getNavigationGroup(): string
    {
        return __('general.conference');
    }

    public static function getModelLabel(): string
    {
        return __('general.announcement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-speaker-wave';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('translate');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                MultilanguageComponent::make([
                    TextInput::make('title')
                        ->label(__('general.title'))
                        ->required(),
                    Textarea::make('meta.summary')
                        ->label(__('general.summary'))
                        ->autosize(),
                    TinyEditor::make('meta.content')
                        ->label(__('general.announcement'))
                        ->profile('basic')
                        ->helperText(__('general.complete_announcement_content')),
                ]),
                DatePicker::make('expires_at')
                    ->label(__('general.expires_at'))
                    ->minDate(today()->addDay()),
                Checkbox::make('send_email')
                    ->label(__('general.send_email_to_subscribed_users'))
                    ->hidden(fn (?Announcement $record) => $record),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(5)
            ->columns([
                IndexColumn::make('no'),
                TextColumn::make('title')
                    ->label(__('general.title'))
                    ->formatStateUsing(fn (Announcement $record) => $record->translated('title'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereTranslatableLike('title', $search))
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('general.expires_at'))
                    ->sortable()
                    ->date(Setting::get('format_date')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('view')
                    ->label(__('general.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => route('livewirePageGroup.scheduledConference.pages.announcement-page', [
                        'announcement' => $record->id,
                    ]))
                    ->color('gray'),
                EditAction::make()
                    ->mutateRecordDataUsing(function (Announcement $record, array $data) {
                        $data['meta'] = $record->getAllMeta()->toArray();

                        return $record->fillTranslatableFormState($data, ['title', 'meta.summary', 'meta.content']);
                    })
                    ->using(fn (Announcement $record, array $data) => AnnouncementUpdateAction::run($record, $data)),
                DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnnouncements::route('/'),
        ];
    }
}
