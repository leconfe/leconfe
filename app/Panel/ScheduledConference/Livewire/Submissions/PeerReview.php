<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions;

use App\Actions\Submissions\NotifySubmissionRevisionRequestAction;
use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Classes\Log;
use App\Constants\SubmissionFileCategory;
use App\Forms\Components\TinyEditor;
use App\Mail\Templates\AcceptPaperMail;
use App\Mail\Templates\DeclinePaperMail;
use App\Mail\Templates\RevisionRequestMail;
use App\Managers\PaymentManager;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\PaymentFee;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionReviewRound;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions\PeerReviewDiscussionTopic;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\ReviewFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\RevisionFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerList;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;
use Squire\Models\Currency;

class PeerReview extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public Submission $submission;

    public ?int $selectedRoundId = null;

    protected $listeners = [
        'refreshSubmission' => '$refresh',
    ];

    public function mount(Submission $submission): void
    {
        $this->submission = $submission;

        if (
            $this->submission->stage->is(SubmissionStage::PeerReview) &&
            in_array($this->submission->status, [SubmissionStatus::OnReview, SubmissionStatus::OnPayment], true) &&
            ! $this->submission->reviewRounds()->exists() &&
            auth()->user()?->can('assignReviewer', $this->submission)
        ) {
            StartSubmissionReviewRoundAction::run($this->submission, [], auth()->user());
            $this->submission->refresh();
        }

        $this->selectedRoundId = $this->submission->activeReviewRound?->getKey()
            ?? $this->submission->latestReviewRound?->getKey();
    }

    public function getReviewRoundsProperty(): Collection
    {
        return $this->submission->reviewRounds()
            ->orderBy('round_number')
            ->get();
    }

    public function getSelectedRoundProperty(): ?SubmissionReviewRound
    {
        if (! $this->selectedRoundId) {
            return null;
        }

        return $this->reviewRounds->firstWhere('id', $this->selectedRoundId);
    }

    public function isSelectedRoundActionable(): bool
    {
        if (! $this->selectedRoundId) {
            return false;
        }

        $activeRoundId = $this->submission->reviewRounds()
            ->open()
            ->orderByDesc('round_number')
            ->value('id');

        return $activeRoundId && (int) $activeRoundId === $this->selectedRoundId;
    }

    public function isSelectedRoundDecisionContext(): bool
    {
        $latestRoundId = $this->submission->reviewRounds()
            ->orderByDesc('round_number')
            ->value('id');

        if (! $latestRoundId) {
            return true;
        }

        if (! $this->selectedRoundId) {
            return false;
        }

        $activeRoundId = $this->submission->reviewRounds()
            ->open()
            ->orderByDesc('round_number')
            ->value('id');

        if ($activeRoundId) {
            return (int) $activeRoundId === $this->selectedRoundId;
        }

        return (int) $latestRoundId === $this->selectedRoundId;
    }

    public function selectRound(int $roundId): void
    {
        if (! $this->reviewRounds->pluck('id')->contains($roundId)) {
            return;
        }

        $this->selectedRoundId = $roundId;
        $this->dispatchSelectedRound();
    }

    #[On('peer-review-round-selected')]
    public function onReviewRoundSelected(int $roundId): void
    {
        $this->selectedRoundId = $roundId;
    }

    protected function dispatchSelectedRound(): void
    {
        if (! $this->selectedRoundId) {
            return;
        }

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(ReviewFiles::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(RevisionFiles::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(PeerReviewDiscussionTopic::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(ReviewerList::class);
    }

    protected function reviewsEmailMessage(): string
    {
        return $this->submission->getReviewsEmailMessage($this->selectedRoundId);
    }

    public function getReviewableFilesProperty(): Collection
    {
        $query = $this->submission
            ->submissionFiles()
            ->with(['media', 'type'])
            ->where(function ($query) {
                if ($this->selectedRoundId) {
                    $query->where(function ($paperQuery) {
                        $paperQuery->where('category', SubmissionFileCategory::REVIEW_FILES)
                            ->where('review_round_id', $this->selectedRoundId);
                    })->orWhere(function ($revisionQuery) {
                        $revisionQuery->where('category', SubmissionFileCategory::REVISION_FILES)
                            ->where('review_round_id', $this->selectedRoundId);
                    });
                } else {
                    $query->where('category', SubmissionFileCategory::REVIEW_FILES);
                }
            });

        return $query->get();
    }

    public function startNextReviewRoundAction(): Action
    {
        return Action::make('startNextReviewRoundAction')
            ->authorize('assignReviewer', $this->submission)
            ->icon('heroicon-o-arrow-path')
            ->color('primary')
            ->outlined()
            ->label(__('general.start_next_review_round'))
            ->modalHeading(__('general.start_next_review_round'))
            ->modalDescription(__('general.start_next_review_round_modal_description'))
            ->modalSubmitActionLabel(__('general.start_next_review_round_modal_submit'))
            ->modalWidth(MaxWidth::ExtraLarge)
            ->closeModalByClickingAway()
            ->extraAttributes(['class' => 'w-full'], true)
            ->hidden(fn (): bool => ! $this->isSelectedRoundActionable())
            ->form([
                TextInput::make('name')
                    ->label(__('general.review_round_name'))
                    ->maxLength(255),
                CheckboxList::make('default_file_ids')
                    ->label(__('general.files_to_include_in_this_round'))
                    ->options(
                        fn () => $this->reviewableFiles
                            ->mapWithKeys(fn (SubmissionFile $file) => [$file->getKey() => $file->media?->name ?? 'File #'.$file->getKey()])
                            ->toArray()
                    )
                    ->descriptions(
                        fn () => $this->reviewableFiles
                            ->mapWithKeys(fn (SubmissionFile $file) => [$file->getKey() => $file->type->name.' ('.$file->category.')'])
                            ->toArray()
                    ),
            ])
            ->successNotificationTitle(__('general.review_round_started'))
            ->action(function (Action $action, array $data) {
                if (! $this->isSelectedRoundActionable()) {
                    $action->failureNotificationTitle(__('general.error'));
                    $action->failure();

                    return;
                }

                $reviewRound = StartSubmissionReviewRoundAction::run(
                    $this->submission,
                    $data['default_file_ids'] ?? [],
                    auth()->user(),
                    $data['name'] ?? null,
                );

                Log::make(
                    name: 'submission',
                    subject: $this->submission,
                    description: __('general.review_round_started_activity', ['number' => $reviewRound->round_number]),
                    event: 'submission-review-round-started',
                )
                    ->by(auth()->user())
                    ->save();

                $this->selectedRoundId = $reviewRound->getKey();
                $this->dispatchSelectedRound();

                $action->success();
            });
    }

    public function renameReviewRoundAction(): Action
    {
        return Action::make('renameReviewRoundAction')
            ->authorize('assignReviewer', $this->submission)
            ->icon('heroicon-o-pencil-square')
            ->label(__('general.rename_review_round'))
            ->modalHeading(__('general.rename_review_round'))
            ->modalSubmitActionLabel(__('general.save'))
            ->modalWidth(MaxWidth::Medium)
            ->successNotificationTitle(__('general.review_round_renamed'))
            ->fillForm(function (array $arguments): array {
                $roundId = (int) ($arguments['round'] ?? 0);

                if (! $this->selectedRoundId || $roundId !== $this->selectedRoundId) {
                    return [
                        'name' => '',
                    ];
                }

                $reviewRound = $this->submission
                    ->reviewRounds()
                    ->whereKey($roundId)
                    ->first();

                return [
                    'name' => $reviewRound?->name ?? '',
                ];
            })
            ->form([
                TextInput::make('name')
                    ->label(__('general.review_round_name'))
                    ->placeholder(__('general.review_round_name_placeholder'))
                    ->helperText(__('general.review_round_name_helper'))
                    ->maxLength(255),
            ])
            ->action(function (Action $action, array $data, array $arguments) {
                $roundId = (int) ($arguments['round'] ?? 0);
                $reviewRound = $this->submission
                    ->reviewRounds()
                    ->whereKey($roundId)
                    ->first();

                if (! $reviewRound || ! $this->selectedRoundId || $roundId !== $this->selectedRoundId) {
                    $action->failureNotificationTitle(__('general.error'));
                    $action->failure();

                    return;
                }

                $name = trim((string) data_get($data, 'name', ''));

                $reviewRound->update([
                    'name' => filled($name) ? $name : null,
                ]);

                $this->selectedRoundId = $reviewRound->getKey();
                $this->submission->refresh();

                $action->success();
            });
    }

    public function declineSubmissionAction()
    {
        return Action::make('declineSubmissionAction')
            ->icon('lineawesome-times-solid')
            ->authorize('declinePaper', $this->submission)
            ->label(__('general.decline_submission'))
            ->color('danger')
            ->outlined()
            ->hidden(fn (): bool => ! $this->isSelectedRoundDecisionContext())
            ->mountUsing(function (Form $form) {
                $mailTemplate = DefaultMailTemplate::where('mailable', DeclinePaperMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                Fieldset::make('Notification')
                    ->columns(1)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Actions::make([
                            FormAction::make('add_reviews_to_email')
                                ->icon('heroicon-m-plus')
                                ->action(fn (Set $set, Get $get) => $set('message', $get('message').$this->reviewsEmailMessage())),
                        ]),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->action(function (Action $action, array $data) {
                if (! $this->isSelectedRoundDecisionContext()) {
                    $action->failureNotificationTitle(__('general.error'));
                    $action->failure();

                    return;
                }

                $this->submission->state()->decline();

                if (! $data['do-not-notify-author']) {
                    try {
                        Mail::to($this->submission->user->email)
                            ->send(
                                (new DeclinePaperMail($this->submission))
                                    ->subjectUsing($data['subject'])
                                    ->contentUsing($data['message'])
                            );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function acceptSubmissionAction()
    {
        return Action::make('acceptSubmissionAction')
            ->authorize('acceptPaper', $this->submission)
            ->icon('lineawesome-check-circle-solid')
            ->color('primary')
            ->label(__('general.accept_submission'))
            ->modalSubmitActionLabel(__('general.accept'))
            ->hidden(fn (): bool => ! $this->isSelectedRoundDecisionContext())
            ->mountUsing(function (Form $form) {
                $mailTemplate = DefaultMailTemplate::where('mailable', AcceptPaperMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                Fieldset::make('Notification')
                    ->label(__('general.notification'))
                    ->columns(1)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Actions::make([
                            FormAction::make('add_reviews_to_email')
                                ->icon('heroicon-m-plus')
                                ->action(fn (Set $set, Get $get) => $set('message', $get('message').$this->reviewsEmailMessage())),
                        ]),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
                Grid::make()
                    ->visible(fn () => ! $this->submission->payment && app()->getCurrentScheduledConference()->getMeta('submission_payment'))
                    ->schema([
                        Radio::make('payment_fee_id')
                            ->label('Payment Fee')
                            ->required()
                            ->options(
                                fn () => PaymentFee::type(PaymentManager::TYPE_SUBMISSION_FEE)
                                    ->active()
                                    ->get()
                                    ->mapWithKeys(function ($record) {
                                        return [
                                            $record->getKey() => $record->name.' ('.money($record->amount, $record->currency, true)->formatWithoutZeroes().')',
                                        ];
                                    })
                            )
                            ->afterStateUpdated(function (Set $set, $state) {
                                if (! $state) {
                                    return;
                                }

                                $paymentFee = PaymentFee::find($state);
                                $set('currency', $paymentFee->currency);
                                $set('amount', $paymentFee->amount);
                                $set('description', $paymentFee->getMeta('description'));
                            })
                            ->reactive(),
                        Grid::make(1)
                            ->visible(fn (Get $get) => $get('payment_fee_id'))
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        Select::make('currency')
                                            ->label(__('general.currency'))
                                            ->formatStateUsing(fn ($state) => ($state !== null) ? ($state !== 'free' ? $state : null) : null)
                                            ->options(
                                                fn () => Currency::query()->orderBy('code_numeric', 'asc')
                                                    ->get()
                                                    ->mapWithKeys(function (?Currency $value, int $key) {
                                                        $currencyCode = Str::upper($value->id);
                                                        $currencyName = $value->name;

                                                        return [$value->id => "($currencyCode) $currencyName"];
                                                    })
                                            )
                                            ->searchable()
                                            ->required(),
                                        TextInput::make('amount')
                                            ->label('Amount')
                                            ->numeric()
                                            ->required()
                                            ->minValue(0),
                                    ]),
                                Textarea::make('description'),
                            ]),
                    ]),
            ])
            ->action(function (Action $action, array $data) {
                if (! $this->isSelectedRoundDecisionContext()) {
                    $action->failureNotificationTitle(__('general.error'));
                    $action->failure();

                    return;
                }

                $this->submission->state()->sendToPresentation();

                if (! $data['do-not-notify-author']) {
                    try {
                        Mail::to($this->submission->user->email)
                            ->send(
                                (new AcceptPaperMail($this->submission))
                                    ->subjectUsing($data['subject'])
                                    ->contentUsing($data['message'])
                            );
                    } catch (\Exception $e) {
                        $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                        $action->failure();
                    }
                }
                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function requestRevisionAction()
    {
        return Action::make('requestRevisionAction')
            ->authorize('requestRevision', $this->submission)
            ->icon('lineawesome-list-alt-solid')
            ->outlined()
            ->color(Color::Orange)
            ->label(__('general.request_revision'))
            ->hidden(fn (): bool => ! $this->isSelectedRoundDecisionContext())
            ->mountUsing(function (Form $form): void {
                $mailTemplate = DefaultMailTemplate::where('mailable', RevisionRequestMail::class)->first();
                $form->fill([
                    'email' => $this->submission->user->email,
                    'subject' => $mailTemplate ? $mailTemplate->subject : '',
                    'message' => $mailTemplate ? $mailTemplate->html_template : '',
                ]);
            })
            ->form([
                Fieldset::make('Notification')
                    ->label(__('general.notification'))
                    ->columns(1)
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->readOnly()
                            ->dehydrated(),
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->required(),
                        TinyEditor::make('message')
                            ->label(__('general.message'))
                            ->minHeight(300)
                            ->profile('email')
                            ->columnSpanFull(),
                        Actions::make([
                            FormAction::make('add_reviews_to_email')
                                ->icon('heroicon-m-plus')
                                ->action(fn (Set $set, Get $get) => $set('message', $get('message').$this->reviewsEmailMessage())),
                        ]),
                        Checkbox::make('do-not-notify-author')
                            ->label(__('general.dont_send_notification_to_author'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->successNotificationTitle(__('general.revision_requested'))
            ->action(function (Action $action, array $data) {
                if (! $this->isSelectedRoundDecisionContext()) {
                    $action->failureNotificationTitle(__('general.error'));
                    $action->failure();

                    return;
                }

                $this->submission->state()->requestRevision();
                $this->submission->refresh();

                try {
                    NotifySubmissionRevisionRequestAction::run(
                        $this->submission,
                        $data['subject'],
                        $data['message'],
                        ! data_get($data, 'do-not-notify-author', false),
                        auth()->user(),
                    );
                } catch (\Exception $e) {
                    $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                    $action->failure();

                    return;
                }

                $action->successRedirectUrl(
                    SubmissionResource::getUrl('view', [
                        'record' => $this->submission->getKey(),
                    ])
                );

                $action->success();
            });
    }

    public function render()
    {
        if (! in_array($this->submission->stage, [
            SubmissionStage::PeerReview,
            SubmissionStage::Presentation,
            SubmissionStage::Editing,
            SubmissionStage::Proceeding,
        ])) {
            return view('panel.scheduledConference.livewire.submissions.message', ['message' => 'Stage not initiated']);
        }

        if ($this->submission->skipped_review) {
            return view('panel.scheduledConference.livewire.submissions.message', ['message' => __('general.review_skipped')]);
        }

        return view('panel.scheduledConference.livewire.submissions.peer-review', [
            'submissionDecision' => in_array($this->submission->status, [
                SubmissionStatus::Editing,
                SubmissionStatus::Declined,
                SubmissionStatus::OnPresentation,
            ]),
        ]);
    }
}
