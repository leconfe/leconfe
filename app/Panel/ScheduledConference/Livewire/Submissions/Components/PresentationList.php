<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Enums\PresentationType;
use App\Models\Presentation;
use App\Models\Submission;
use App\Panel\ScheduledConference\Pages\PresentationDetail;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Actions\EditAction;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;

class PresentationList extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public bool $viewOnly = false;

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.presentation-list');
    }

    public function getQuery(): Builder
    {
        return $this->submission->presentations()
            ->with(['media'])
            ->getQuery();
    }

    public function form(Form $form)
    {
        return $form->schema([
            SpatieMediaLibraryFileUpload::make('thumbnail')
                ->collection('thumbnail')
                ->image(),
            Select::make('type')
                ->options(PresentationType::class)
                ->required()
                ->default(PresentationType::GoogleSlide->value)
                ->live(),
            Grid::make(1)
                ->schema([
                    SpatieMediaLibraryFileUpload::make('file')
                        ->required()
                        ->collection('pdf')
                        ->disk('private-files')
                        ->maxFiles(1)
                        ->visible(fn(Get $get) => PresentationType::PDF->is((int) $get('type')))
                        ->dehydrated(fn(Get $get) => PresentationType::PDF->is((int) $get('type')))
                        ->acceptedFileTypes(['application/pdf']),
                    TextInput::make('meta.youtube_video_id')
                        ->label('Youtube URL')
                        ->required()
                        ->prefix('https://www.youtube.com/watch?v=')
                        ->visible(fn(Get $get) => PresentationType::Youtube->is((int) $get('type')))
                        ->dehydrated(fn(Get $get) => PresentationType::Youtube->is((int) $get('type'))),
                    TextInput::make('meta.google_slide_url')
                        ->label('Google Slide Published URL')
                        ->regex('/^https:\/\/docs\.google\.com\/presentation\/d\/e\/[A-Za-z0-9_-]+\/pub(embed)?(\?.*)?$/')
                        ->required()
                        ->visible(fn(Get $get) => PresentationType::GoogleSlide->is((int) $get('type')))
                        ->dehydrated(fn(Get $get) => PresentationType::GoogleSlide->is((int) $get('type')))
                        ->dehydrateStateUsing(function (string $state) {
                            if (str_contains($state, '/pubembed?')) {
                                return $state;
                            }

                            if (str_contains($state, '/pub?')) {
                                return str_replace('/pub?', '/pubembed?', $state);
                            }

                            return $state;
                        }),
                ]),
            Checkbox::make('is_final')
                ->label('Set as Final Presentation'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Presentation')
            ->query($this->getQuery())
            ->columns([
                SpatieMediaLibraryImageColumn::make('thumbnail')
                    ->collection('thumbnail'),
                TextColumn::make('type'),
                IconColumn::make('is_final')
                    ->label('Final Presentation')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make('add')
                    ->label('Add Presentation')
                    ->icon('heroicon-o-plus')
                    ->outlined()
                    ->modalWidth(MaxWidth::ExtraLarge)
                    ->form(fn(Form $form) => $this->form($form))
                    ->using(function (array $data) {
                        $record = $this->submission->presentations()->create([
                            'type' => $data['type'],
                        ]);

                        if (PresentationType::from($data['type'])->isOneOf(PresentationType::Youtube, PresentationType::GoogleSlide)) {
                            $record->setManyMeta($data['meta']);
                        }

                        if ($data['is_final'] && !$record->is_final) {
                            $record->setAsFinal();
                        }

                        $record->fetchThumbnailAutomatically();


                        return $record;
                    })

            ])
            ->actions([
                ActionGroup::make([
                    Action::make('preview')
                        ->icon('heroicon-o-eye')
                        ->url(fn(Presentation $record) => PresentationDetail::getUrl(['record' => $record->getKey()]), shouldOpenInNewTab: true),
                    Action::make('set_as_final')
                        ->label('Set as Final Presentation')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn(Presentation $record) => !$record->is_final)
                        ->action(function (Presentation $record, Action $action) {
                            $record->setAsFinal();

                            $action->successNotificationTitle('This presentation is now set as Final and will be visible to participants.');
                            $action->success();
                        }),
                    EditAction::make()
                        ->modalWidth(MaxWidth::ExtraLarge)
                        ->form(fn($form) => $this->form($form))
                        ->mutateRecordDataUsing(function (array $data, Presentation $record) {
                            $data['meta'] = $record->getAllMeta()->toArray();

                            return $data;
                        })
                        ->using(function (Presentation $record, array $data) {
                            $record->update([
                                'type' => $data['type'],
                            ]);

                            if (PresentationType::from($data['type'])->isOneOf(PresentationType::Youtube, PresentationType::GoogleSlide)) {
                                $record->setManyMeta($data['meta']);
                            }

                            $record->fetchThumbnailAutomatically();
                            if ($data['is_final'] && !$record->is_final) {
                                $record->setAsFinal();
                            }

                            return $record;
                        }),
                    Action::make('generate_thumbnail')
                        ->label('Generate Thumbnail')
                        ->visible(fn(Presentation $record) => $record->type === PresentationType::Youtube)
                        ->icon('heroicon-o-photo')
                        ->requiresConfirmation()
                        ->action(function (Presentation $record) {
                            $record->fetchThumbnailAutomatically(true);
                        }),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public function submit() {}
}
