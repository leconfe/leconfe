<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\PublisherLibrary\PublisherLibraryCreateAction;
use App\Actions\PublisherLibrary\PublisherLibraryUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Frontend\ScheduledConference\Pages\PublisherLibrary as PublisherLibraryPage;
use App\Models\Media;
use App\Models\ScheduledConference;
use App\Tables\Columns\IndexColumn;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;

class PublisherLibrary extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Media::query()
                    ->where('model_type', ScheduledConference::class)
                    ->where('model_id', app()->getCurrentScheduledConferenceId())
                    ->where('collection_name', 'publisher-library'),
            )
            ->defaultSort('order_column', 'asc')
            ->reorderable('order_column')
            ->columns([
                IndexColumn::make('no')
                    ->label(__('general.no')),
                TextColumn::make('name')
                    ->label(__('general.name'))
                    ->getStateUsing(fn (Media $record) => $record->getLocalizedMeta('name'))
                    ->searchable()
                    ->action(fn (Media $record) => $record),
                ToggleColumn::make('public_access')
                    ->label(__('general.public_access'))
                    ->getStateUsing(fn (Media $record) => $record->getCustomProperty('is_public'))
                    ->updateStateUsing(function (Media $record, $state) {
                        $record->setCustomProperty('is_public', $state);
                        $record->save();
                    }),

            ])
            ->headerActions([
                Action::make('view_page')
                    ->label(__('general.view_page'))
                    ->outlined()
                    ->icon('heroicon-o-eye')
                    ->url(route(PublisherLibraryPage::getRouteName('scheduledConference')))
                    ->openUrlInNewTab(),
                Action::make('add_a_file')
                    ->label(__('general.add_a_file'))
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->icon('heroicon-o-plus')
                    ->action(function (array $data) {
                        $currentScheduledConference = app()->getCurrentScheduledConference();
                        $currentLocale = app()->getLocale();

                        $name = data_get($data, "meta.name.$currentLocale") 
                            ?? data_get($data, "meta.name." . app()->getFallbackLocale()) 
                            ?? collect(data_get($data, 'meta.name', []))->first();

                        $media = $currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
                            ->usingName($name)
                            ->withCustomProperties(data_get($data, 'custom', []))
                            ->toMediaCollection('publisher-library', 'private-files');

                        return PublisherLibraryCreateAction::run([
                            'id' => $media->id,
                            'meta' => data_get($data, 'meta')
                        ]);
                    })
                    ->form(fn ($form) => $this->form($form)),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->form(fn ($form, $record) => $this->form($form))
                        ->mutateRecordDataUsing(function (array $data, Media $record) {
                            $data['meta'] = $record->getAllMeta();
                            $data['file_name'] = [$record->file_name];
                            $data['custom']['is_public'] = $record->getCustomProperty('is_public');

                            return $data;
                        })
                        ->action(function (Media $record, array $data) {
                            $currentScheduledConference = app()->getCurrentScheduledConference();
                            $currentLocale = app()->getLocale();

                            $name = data_get($data, "meta.name.$currentLocale") 
                                ?? data_get($data, "meta.name." . app()->getFallbackLocale()) 
                                ?? collect(data_get($data, 'meta.name', []))->first();

                            if (Storage::disk('local')->exists(data_get($data, 'file_name'))) {
                                $media = $currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
                                    ->usingName($name)
                                    ->withCustomProperties(data_get($data, 'custom', []))
                                    ->toMediaCollection('publisher-library', 'private-files');

                                $media->uuid = $record->uuid;
                                $media->order_column = $record->order_column;
                                $media->created_at = $record->created_at;
                                $media->save();

                                $record->delete();
                                $record = $media;
                            } else {
                                $record->name = $name;
                                $record->setCustomProperty('is_public', data_get($data, 'custom.is_public', false));
                                $record->save();
                            }

                            return PublisherLibraryUpdateAction::run($record, $data);
                        }),
                    Action::make('download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->label(__('general.download'))
                        ->action(fn (Media $record) => $record),
                    DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Form $form)
    {
        return $form
            ->schema([
                MultilanguageComponent::make([
                    TextInput::make('meta.name')
                    ->required(),
                ]),
                
                FileUpload::make('file_name')
                    ->disk('local')
                    // ->preserveFilenames()
                    ->afterStateHydrated(static function (BaseFileUpload $component, ?Media $record): void {
                        if (blank($record)) {
                            $component->state([]);

                            return;
                        }

                        $component->state([((string) Str::uuid()) => $record->file_name]);
                    })
                    ->downloadable()
                    ->getUploadedFileUsing(static function (BaseFileUpload $component, ?Media $record): ?array {
                        if (blank($record)) {
                            return null;
                        }

                        $url = null;
                        try {
                            $url = $record?->getTemporaryUrl(
                                now()->addMinutes(5),
                                options: ['disk' => $record?->disk]
                            );
                        } catch (\Throwable $exception) {
                            // This driver does not support creating temporary URLs.
                        }

                        $url ??= $record?->getUrl();

                        return [
                            'name' => $record?->getAttributeValue('file_name'),
                            'size' => $record?->getAttributeValue('size'),
                            'type' => $record?->getAttributeValue('mime_type'),
                            'url' => $url,
                        ];
                    })
                    ->required(),
                Checkbox::make('custom.is_public')
                    ->label(__('general.allow_public_access')),
            ]);
    }

    public function render()
    {
        return view('tables.table');
    }
}
