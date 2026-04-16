<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Models\Proceeding;
use App\Models\Submission;
use Filament\Forms\Components\Select;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\Log;

class SubmissionProceeding extends \Livewire\Component implements HasForms, HasInfolists
{
    use InteractsWithForms, InteractsWithInfolists;

    public Submission $submission;

    public array $formData = [];

    protected $listeners = [
        'refreshSubmissionProceeding' => '$refresh',
    ];

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.submission-proceeding');
    }

    public function mount(Submission $submission)
    {
        $this->form->fill([
            'meta' => [
                'article_pages' => $this->submission->getMeta('article_pages'),
                'isbn' => $this->submission->getMeta('isbn'),
            ],
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->submission)
            ->schema([
                TextEntry::make('id')
                    ->label(__('general.proceeding'))
                    ->html()
                    ->getStateUsing(function (Submission $record) {
                        if ($record->proceeding) {
                            $proceedingTitle = $record->proceeding->title;
                            $proceedingRoute = route('filament.conference.resources.proceedings.view', ['record' => $record->proceeding]);

                            return __(
                                'general.proceeding_route_title',
                                ['route' => $proceedingRoute, 'variable' => $proceedingTitle]
                            );
                        }

                        return __('general.submission_not_yet_publication');
                    })
                    ->suffixActions([
                        Action::make('assign_proceeding')
                            ->button()
                            ->label(fn (Submission $record) => $record->proceeding ? __('general.change_proceeding') : __('general.assign_to_proceeding'))
                            ->visible(fn (Submission $record) => auth()->user()->can('editing', $record))
                            ->modalWidth(MaxWidth::ExtraLarge)
                            ->form(fn (Submission $record) => static::getFormAssignProceeding($record))
                            ->action(fn (Submission $record, array $data) => static::assignProceeding($record, $data)),
                    ]),
            ]);
    }

    public static function getFormAssignProceeding(Submission $submission): array
    {
        return [
            Select::make('proceeding_id')
                ->label(__('general.proceeding'))
                ->placeholder(__('general.none'))
                ->formatStateUsing(fn () => $submission->proceeding_id ?? null)
                // ->native(false)
                // ->searchable()
                ->options(
                    fn () => [
                        __('general.future_proceedings') => Proceeding::query()
                            ->where('published', false)
                            ->pluck('title', 'id')
                            ->toArray(),
                        __('general.back_proceedings') => Proceeding::query()
                            ->where('published', true)
                            ->pluck('title', 'id')
                            ->toArray(),
                    ]
                ),
        ];
    }

    public static function assignProceeding(Submission $submission, array $data)
    {
        $data['proceeding_id'] ? $submission->assignProceeding($data['proceeding_id']) : $submission->unassignProceeding();
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->submission)
            ->disabled(function (): bool {
                return ! auth()->user()->can('editing', $this->submission);
            })
            ->schema([
                Select::make('track_id')
                    ->required()
                    ->relationship('track', 'title')
                    ->label(__('general.track')),
                TextInput::make('meta.isbn')
                    ->label("ISBN"),
                SpatieMediaLibraryFileUpload::make('media.cover')
                    ->label(__('general.cover_image'))
                    ->collection('cover')
                    ->model($this->submission)
                    ->image()
                    ->preserveFilenames(),
                TextInput::make('meta.article_pages')
                    ->label(__('general.pages'))
                    ->maxWidth('xs')
                    ->placeholder(__('general.eg_1_10')),
            ])
            ->statePath('formData');
    }

    public function submit()
    {
        $data = $this->form->getState();
        try {
            $submission = SubmissionUpdateAction::run(
                $data,
                $this->submission
            );

            $this->form->model($submission)->saveRelationships();

            Notification::make()
                ->success()
                ->title(__('general.saved'))
                ->send();
        } catch (\Throwable $th) {
            Notification::make()
                ->danger()
                ->title(__('general.error'))
                ->body(__('general.there_was_error_please_contact_administrator'))
                ->send();

            Log::error($th);
        }
    }
}
