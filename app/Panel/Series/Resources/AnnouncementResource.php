<?php

namespace App\Panel\Series\Resources;

use App\Actions\Announcements\AnnouncementCreateAction;
use App\Actions\Announcements\AnnouncementUpdateAction;
use App\Facades\Setting;
use App\Models\Announcement;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use App\Panel\Series\Resources\AnnouncementResource\Pages;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\Textarea;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    // protected static ?string $modelLabel = 'Announcement';

    // protected static ?string $navigationGroup = 'Conference';

    public static function getModelLabel(): string
    {
        return __('translation.announcementResource.announcementResourceModelLabel');
    }

    public static function getNavigationGroup(): string
    {
        return __('translation.announcementResource.announcementResourceNavigationGroup');
    }


    protected static ?string $navigationIcon = 'heroicon-o-speaker-wave';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label(__('translation.announcementResource.announcementResourceLabelTitle'))
                    ->required(),
                SpatieMediaLibraryFileUpload::make('featured_image')
                    ->collection('featured_image')
                    ->label(__('translation.announcementResource.announcementResourceLabelFeaturedImage'))
                    ->image(),
                Textarea::make('meta.summary')
                    ->rows(5)
                    ->label(__('translation.announcementResource.announcementResourceLabelSummary')),
                TinyEditor::make('meta.content')
                    ->label(__('translation.announcementResource.announcementResourceLabelAnnouncement'))
                    ->minHeight(600)
                    ->helperText(__('translation.announcementResource.announcementResourceHelperTextAnnouncement')),
                DatePicker::make('expires_at')
                    ->label(__('translation.announcementResource.announcementResourceLabelExpiresAt'))
                    ->minDate(today()->addDay()),
                Checkbox::make('send_email')
                    ->label(__('translation.announcementResource.announcementResourceLabelCheckBox'))
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
                    ->label(__('translation.announcementResource.announcementResourceLabelTitle'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label(__('translation.announcementResource.announcementResourceLabelExpiresAt'))
                    ->sortable()
                    ->date(Setting::get('format_date')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('view')
                    ->label(__('translation.button.view'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) =>  route('livewirePageGroup.conference.pages.announcement-page', [
                        'announcement' => $record->id,
                    ]))
                    ->color('gray'),
                EditAction::make()
                    ->mutateRecordDataUsing(function (Announcement $record, array $data) {
                        $data['meta'] = $record->getAllMeta()->toArray();

                        return $data;
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
