<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Actions\Submissions\UpdateSubmissionPublicationDateAction;
use App\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Models\Proceeding;
use App\Models\Submission;
use App\Models\Track;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $this->fillProceedingForm();
    }

    protected function fillProceedingForm(): void
    {
        $this->form->fill([
            'published_at' => $this->submission->published_at?->toDateString(),
            'track_id' => $this->submission->track_id,
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
                            $proceedingTitle = e($record->proceeding->translated('title'));
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
                            ->with('translate')
                            ->get()
                            ->mapWithKeys(fn (Proceeding $proceeding) => [$proceeding->getKey() => $proceeding->translated('title')])
                            ->toArray(),
                        __('general.back_proceedings') => Proceeding::query()
                            ->where('published', true)
                            ->with('translate')
                            ->get()
                            ->mapWithKeys(fn (Proceeding $proceeding) => [$proceeding->getKey() => $proceeding->translated('title')])
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
            ->schema([
                DatePicker::make('published_at')
                    ->label(__('general.date_published'))
                    ->format('Y-m-d')
                    ->maxDate(now())
                    ->native()
                    ->fieldWrapperView('forms.publication-date-field-wrapper')
                    ->extraInputAttributes(fn (DatePicker $component): array => [
                        'aria-describedby' => $component->getId().'-description',
                    ])
                    ->disabled(fn (): bool => ! auth()->user()->can('updatePublicationDate', $this->submission)),
                Group::make([
                    Select::make('track_id')
                        ->required()
                        ->relationship('track', 'title', fn ($query) => $query->with('translate'))
                        ->getOptionLabelFromRecordUsing(fn (Track $record) => $record->translated('title'))
                        ->label(__('general.track')),
                    TextInput::make('meta.isbn')
                        ->label('ISBN'),
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
                ])->disabled(fn (): bool => ! auth()->user()->can('editing', $this->submission)),
            ])
            ->statePath('formData');
    }

    public function submit()
    {
        $this->submission = $this->submission->fresh();
        $canEdit = Gate::allows('editing', $this->submission);

        if (! $canEdit && ! Gate::allows('updatePublicationDate', $this->submission)) {
            Notification::make()
                ->danger()
                ->title(__('general.submission_proceeding_changes_not_saved'))
                ->send();

            $this->fillProceedingForm();

            return;
        }

        $data = $this->form->getState();
        try {
            DB::transaction(function () use ($data, $canEdit) {
                if (array_key_exists('published_at', $data)) {
                    $this->submission = UpdateSubmissionPublicationDateAction::run($this->submission, $data['published_at']);
                    unset($data['published_at']);
                }

                if ($canEdit) {
                    $this->submission = SubmissionUpdateAction::run($data, $this->submission);
                    $this->form->model($this->submission)->saveRelationships();
                }
            });

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
