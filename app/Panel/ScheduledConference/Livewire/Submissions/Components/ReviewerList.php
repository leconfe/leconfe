<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Actions\Review\ReviewUpdateAction;
use App\Actions\Submissions\StartSubmissionReviewRoundAction;
use App\Classes\Log;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Constants\SubmissionStatusRecommendation;
use App\Forms\Components\TinyEditor;
use App\Mail\Templates\ReviewerCancelationMail;
use App\Mail\Templates\ReviewerInvitationMail;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\ReviewerAssignedFile;
use App\Models\ReviewFormItem;
use App\Models\Role;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\SubmissionReviewRound;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\PeerReview;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions\PeerReviewDiscussionTopic;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\PaperFiles;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\Files\RevisionFiles;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Awcodes\Shout\Components\Shout;
use Filament\Actions\Action as FilamentAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Livewire;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Livewire\Attributes\On;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class ReviewerList extends Component implements HasActions, HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable;

    public Submission $record;

    public Role $reviewerRole;

    public ?int $selectedRoundId = null;

    public function mount(Submission $record)
    {
        $this->record = $record;
        $this->reviewerRole = Role::where('name', UserRole::Reviewer->value)->first();

        if (
            $this->record->stage === SubmissionStage::PeerReview &&
            in_array($this->record->status, [SubmissionStatus::OnReview, SubmissionStatus::OnPayment], true) &&
            ! $this->record->reviewRounds()->exists() &&
            auth()->user()?->can('assignReviewer', $this->record)
        ) {
            StartSubmissionReviewRoundAction::run($this->record, [], auth()->user());
            $this->record->refresh();
        }

        $this->selectedRoundId = $this->record->activeReviewRound?->getKey()
            ?? $this->record->latestReviewRound?->getKey();

        $this->dispatchSelectedRound();
    }

    #[On('peer-review-round-selected')]
    public function onReviewRoundSelected(int $roundId): void
    {
        $this->selectedRoundId = $roundId;
        $this->resetTable();
    }

    public function selectRound(int $roundId): void
    {
        if (! $this->reviewRounds->pluck('id')->contains($roundId)) {
            return;
        }

        $this->selectedRoundId = $roundId;
        $this->resetTable();
        $this->dispatchSelectedRound();
    }

    public function getReviewRoundsProperty(): Collection
    {
        return $this->record->reviewRounds()
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

    public function isSelectedRoundActive(): bool
    {
        return $this->selectedRound?->isOpen() ?? false;
    }

    public function getReviewableFilesProperty(): Collection
    {
        $query = $this->record
            ->submissionFiles()
            ->with(['media', 'type'])
            ->where(function ($query) {
                if ($this->selectedRoundId) {
                    $query->where(function ($paperQuery) {
                        $paperQuery->where('category', SubmissionFileCategory::PAPER_FILES)
                            ->where('review_round_id', $this->selectedRoundId);
                    })->orWhere(function ($revisionQuery) {
                        $revisionQuery->where('category', SubmissionFileCategory::REVISION_FILES)
                            ->where('review_round_id', $this->selectedRoundId);
                    });
                } else {
                    $query->where('category', SubmissionFileCategory::PAPER_FILES);
                }
            });

        return $query->get();
    }

    protected function selectedRoundReviewerIds(): array
    {
        if (! $this->selectedRoundId) {
            return [];
        }

        return Review::query()
            ->where('review_round_id', $this->selectedRoundId)
            ->pluck('user_id')
            ->all();
    }

    protected function ensureSelectedOpenRound(array $defaultFileIds = []): SubmissionReviewRound
    {
        if ($this->selectedRound && $this->selectedRound->isOpen()) {
            return $this->selectedRound;
        }

        $round = StartSubmissionReviewRoundAction::run(
            $this->record,
            $defaultFileIds,
            auth()->user(),
        );

        $this->selectedRoundId = $round->getKey();
        $this->dispatchSelectedRound();

        return $round;
    }

    protected function dispatchSelectedRound(): void
    {
        if (! $this->selectedRoundId) {
            return;
        }

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(PeerReview::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(PaperFiles::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(RevisionFiles::class);

        $this->dispatch('peer-review-round-selected', roundId: $this->selectedRoundId)
            ->to(PeerReviewDiscussionTopic::class);
    }

    public function form(Form $form): Form
    {
        return $form
            ->id('reviewerForm')
            ->schema([
                Select::make('user_id')
                    ->label(__('general.reviewer'))
                    ->placeholder(__('general.select_reviewer'))
                    ->allowHtml()
                    ->hidden(fn ($record) => $record)
                    ->preload()
                    ->required()
                    ->searchable()
                    ->options(function () {
                        return User::with('roles')
                            ->whereHas(
                                'roles',
                                fn (Builder $query) => $query->where('name', UserRole::Reviewer->value)
                            )
                            ->whereNotIn('id', $this->selectedRoundReviewerIds())
                            ->limit(10)
                            ->lazy()
                            ->mapWithKeys(
                                fn (User $user) => [$user->getKey() => static::renderSelectParticipant($user)]
                            )
                            ->toArray();
                    })
                    ->getSearchResultsUsing(function (Get $get, string $search) {
                        return User::with('roles')
                            ->whereHas(
                                'roles',
                                fn (Builder $query) => $query->whereName(UserRole::Reviewer->value)
                            )
                            ->whereNotIn('id', $this->selectedRoundReviewerIds())
                            ->where(function (Builder $query) use ($search) {
                                $query->where('given_name', 'like', "%{$search}%")
                                    ->orWhere('family_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            })
                            ->limit(10)
                            ->lazy()
                            ->mapWithKeys(
                                fn (User $user) => [$user->getKey() => static::renderSelectParticipant($user)]
                            )
                            ->toArray();
                    }),
                CheckboxList::make('papers')
                    ->label(__('general.files_be_to_reviewer'))
                    ->hidden(fn () => $this->reviewableFiles->isEmpty())
                    ->options(function () {
                        return $this->reviewableFiles
                            ->mapWithKeys(function (SubmissionFile $paper) {
                                return [
                                    $paper->getKey() => new HtmlString(
                                        Action::make('paper-'.$paper->getKey())
                                            ->label($paper->media->name)
                                            ->url(fn () => $paper->media->getTemporaryUrl(now()->addMinutes(5)))
                                            ->link()
                                            ->toHtml()
                                    ),
                                ];
                            });
                    })
                    ->descriptions(function () {
                        return $this->reviewableFiles
                            ->mapWithKeys(function (SubmissionFile $paper) {
                                return [$paper->getKey() => $paper->type->name.' ('.$paper->category.')'];
                            });
                    }),
                Fieldset::make('Notification')
                    ->label(__('general.notification'))
                    ->hidden(fn ($record) => $record)
                    ->schema([
                        TextInput::make('subject')
                            ->label(__('general.subject'))
                            ->columnSpanFull(),
                        TinyEditor::make('message')
                            ->minHeight(300)
                            ->profile('email')
                            ->label(__('general.reviewer_invitation_message'))
                            ->columnSpanFull(),
                        Checkbox::make('no-invitation-notification')
                            ->label(__('general.dont_send_notification'))
                            ->columnSpanFull(),
                    ]),
                Grid::make()
                    ->schema([
                        DatePicker::make('meta.response_due_date')
                            ->required(),
                        DatePicker::make('meta.review_due_date')
                            ->required(),
                    ]),
                Radio::make('meta.review_mode')
                    ->required()
                    ->label(__('general.review_mode'))
                    ->options(Review::getModeOptions())
                    ->reactive(),
                Checkbox::make('meta.open_review_for_author')
                    ->visible(fn (Get $get) => in_array($get('meta.review_mode'), [Review::MODE_DOUBLE_ANONYMOUS, Review::MODE_ANONYMOUS])),
            ]);
    }

    public static function renderSelectParticipant(User $user): string
    {
        return view('forms.select-participant', ['participant' => $user])->render();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => $this->record->reviews()->getQuery()
                    ->where('review_round_id', $this->selectedRoundId ?: 0)
                    ->when(
                        $this->record->isParticipantAuthor(auth()->user()),
                        fn ($query) => $query
                            ->whereNotNull('date_completed')
                            ->where(function ($query) {
                                $query->whereHas('meta', function (Builder $q) {
                                    $q->where('key', 'review_mode');
                                    $q->where('value', Review::MODE_OPEN);
                                });
                                $query->orWhereHas('meta', function (Builder $q) {
                                    $q->where('key', 'open_review_for_author');
                                    $q->where('value', true);
                                });
                            })
                    )
            )
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('user.fullName')
                            ->label(__('general.full_name'))
                            ->color(
                                fn (Review $record): string => $record->status == ReviewerStatus::CANCELED ? 'danger' : 'primary'
                            )
                            ->description(
                                fn (Review $record): string => $record->user->email
                            )
                            ->when(
                                $this->record->isParticipantAuthor(auth()->user()),
                                fn ($action) => $action
                                    ->getStateUsing(function (Review $record, \stdClass $rowLoop) {
                                        if ($record->getMeta('review_mode') != Review::MODE_OPEN) {
                                            return 'Reviewer '.$rowLoop->iteration;
                                        }

                                        return $record->user->fullName;
                                    })
                                    ->description('')
                            ),
                        TextColumn::make('status')
                            ->extraAttributes(['class' => 'mt-2'])
                            ->color(function ($state) {
                                return match ($state) {
                                    ReviewerStatus::PENDING => 'warning',
                                    ReviewerStatus::CANCELED, ReviewerStatus::DECLINED => 'danger',
                                    ReviewerStatus::ACCEPTED => 'success',
                                    default => 'primary'
                                };
                            })
                            ->badge(),
                    ]),
                    TextColumn::make('recommendation')
                        ->label(__('general.recommendation'))
                        ->badge()
                        ->formatStateUsing(function ($state, $record) {
                            if (! $record->reviewSubmitted()) {
                                return '';
                            }

                            return __('general.recommend').$state;
                        })
                        ->color(
                            fn (Review $record): string => match ($record->recommendation) {
                                SubmissionStatusRecommendation::ACCEPT => 'primary',
                                SubmissionStatusRecommendation::DECLINE => 'danger',
                                default => 'warning'
                            }
                        ),
                ]),

            ])
            ->actions([
                Action::make('read-review')
                    ->visible(fn (Review $record): bool => $record->reviewSubmitted())
                    ->modalWidth('2xl')
                    ->modalSubmitActionLabel('Confirm')
                    ->modalHeading(fn () => 'Review: '.$this->record->getMeta('title'))
                    ->icon('lineawesome-eye')
                    ->disabledForm()
                    ->when(
                        ! auth()->user()->can('actAsEditor', $this->record),
                        fn ($action) => $action
                            ->modalCancelAction(false)
                            ->modalSubmitAction(false)
                    )
                    ->mountUsing(function (Review $record, Form $form) {
                        $form->disabled(! auth()->user()->can('actAsEditor', $this->record));

                        $form->fill([
                            ...$record->attributesToArray(),
                            'meta' => $record->getAllMeta(),
                        ]);
                    })
                    ->action(function (Review $record, array $data, Action $action) {
                        try {
                            DB::beginTransaction();

                            $data['date_acknowledged'] = now();

                            ReviewUpdateAction::run($record, Arr::only($data, ['date_acknowledged', 'recommendation', 'quality']));

                            DB::commit();
                        } catch (\Throwable $th) {
                            DB::rollBack();
                            $action->failureNotificationTitle($th->getMessage());
                            $action->failure();

                            return;
                        }

                        $action->success();
                    })
                    ->form(
                        fn (Form $form, Review $record) => $form
                            ->model($record)
                            ->id('readReview')
                            ->schema([
                                Placeholder::make('')
                                    ->extraAttributes(['class' => 'text-gray-500'])
                                    ->when(
                                        $this->record->isParticipantAuthor(auth()->user()),
                                        fn ($component) => $component->hidden(),
                                    )
                                    ->content('Once this review has been read, press "Confirm" to indicate that the review process may proceed. If the reviewer has submitted their review elsewhere, you may upload the file below and then press "Confirm" to proceed.'),
                                Shout::make('completed')
                                    ->content(fn (Review $record) => 'Completed : '.$record->date_completed),
                                Shout::make('recommendation')
                                    ->color(
                                        fn (): string => match ($record->recommendation) {
                                            SubmissionStatusRecommendation::ACCEPT => 'success',
                                            SubmissionStatusRecommendation::DECLINE => 'danger',
                                            default => 'warning'
                                        }
                                    )
                                    ->content(fn (Review $record) => 'Recommendation : '.$record->recommendation),
                                Placeholder::make('reviewer')
                                    ->when(
                                        $this->record->isParticipantAuthor(auth()->user()),
                                        fn ($component) => $component->visible(fn (Review $record) => $record->getMeta('review_mode') == Review::MODE_OPEN),
                                    )
                                    ->content(fn (Review $record) => $record->user->fullName.' ('.$record->user->email.')'),
                                Placeholder::make('score')
                                    ->label('Paper Score')
                                    ->hidden(fn (Review $record) => ! $record->score)
                                    ->content(fn (Review $record) => $record->score),
                                ...ReviewFormItem::ordered()->lazy()->map(fn (ReviewFormItem $item) => $item->getFormField()->disabled())->toArray(),
                                Section::make('Reviewer Comments')
                                    ->schema([
                                        Placeholder::make('for_author_and_editor')
                                            ->label('For Author and Editor')
                                            ->extraAttributes(['class' => 'prose'])
                                            ->content(fn ($record) => $record->getMeta('review_for_author_editor') ? new HtmlString($record->getMeta('review_for_author_editor')) : '-'),
                                        Placeholder::make('for_editor')
                                            ->label('For Editor')
                                            ->visible(fn () => auth()->user()->can('actAsEditor', $this->record))
                                            ->extraAttributes(['class' => 'prose'])
                                            ->content(fn ($record) => $record->getMeta('review_for_editor') ? new HtmlString($record->getMeta('review_for_editor')) : '-'),
                                    ]),
                                Livewire::make(ReviewerFiles::class, [
                                    'record' => $record,
                                    'viewOnly' => (bool) $record->date_acknowledged || ! auth()->user()->can('actAsEditor', $this->record),
                                ])->lazy(),
                                Select::make('recommendation')
                                    ->required()
                                    ->hidden(! auth()->user()->can('actAsEditor', $this->record))
                                    ->helperText('Set or adjust the reviewer recommendation.')
                                    ->options(SubmissionStatusRecommendation::list()),
                                Select::make('quality')
                                    ->required()
                                    ->hidden(! auth()->user()->can('actAsEditor', $this->record))
                                    ->native(false)
                                    ->label('Reviewer Rating')
                                    ->helperText('Rate the quality of the review provided. This rating is not shared with the reviewer.')
                                    ->options(
                                        collect([1, 2, 3, 4, 5])
                                            ->mapWithKeys(fn ($count) => [$count => view('components.star', ['count' => $count])->render()])
                                            ->prepend('No Rating', 0)
                                            ->toArray()
                                    )
                                    ->allowHtml(),
                            ])
                    ),
                ActionGroup::make([
                    Action::make('edit-reviewer')
                        ->authorize(fn () => auth()->user()->can('editReviewer', $this->record))
                        ->modalWidth('2xl')
                        ->icon('iconpark-edit')
                        ->label(__('general.edit'))
                        ->mountUsing(function (Review $record, Form $form) {
                            $form->fill([
                                'papers' => $record->assignedFiles()->with(['submissionFile'])
                                    ->get()
                                    ->pluck('submission_file_id')
                                    ->toArray(),
                                'meta' => $record->getAllMeta(),
                            ]);
                        })
                        ->form(fn ($form) => $this->form($form))
                        ->successNotificationTitle(__('general.reviewer_updated'))
                        ->action(function (Action $action, Review $record, array $data) {
                            $record->assignedFiles()->get()->each(
                                fn (ReviewerAssignedFile $file) => $file->delete()
                            );

                            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                                $record->setManyMeta($data['meta']);
                            }

                            if (isset($data['papers'])) {
                                collect($data['papers'])
                                    ->each(function (int $submisionFileId) use ($record) {
                                        $record->assignedFiles()->create([
                                            'submission_file_id' => $submisionFileId,
                                        ]);
                                    });
                            }
                            $action->success();
                        }),
                    Action::make('email-reviewer')
                        ->authorize(fn () => auth()->user()->can('emailReviewer', $this->record))
                        ->label(__('general.email_reviewer'))
                        ->icon('iconpark-sendemail')
                        ->modalSubmitActionLabel(__('general.send'))
                        ->mountUsing(function (Form $form, Review $record) {
                            $form->fill([
                                'email' => $record->user->email,
                                'subject' => 'Notification for you',
                            ]);
                        })
                        ->form([
                            TextInput::make('email')
                                ->label(__('general.email'))
                                ->dehydrated()
                                ->disabled(),
                            TextInput::make('subject')
                                ->label(__('general.subject'))
                                ->required(),
                            TinyEditor::make('message')
                                ->label(__('general.message'))
                                ->minHeight(300)
                                ->profile('email'),
                        ])
                        ->successNotificationTitle('Email has been sent.')
                        ->action(function (Action $action, Review $record, array $data) {
                            Mail::send([], [], function (Message $message) use ($record, $data) {
                                $message->to($record->user->email)
                                    ->subject($data['subject'])
                                    ->html($data['message']);
                            });
                            $action->success();
                        }),
                    Action::make('cancel-reviewer')
                        ->color('danger')
                        ->authorize(fn () => auth()->user()->can('cancelReviewer', $this->record))
                        ->icon('iconpark-deletethree-o')
                        ->label(__('general.cancel_reviewer'))
                        ->hidden(
                            fn (Review $record) => $record->status == ReviewerStatus::CANCELED || $record->confirmed()
                        )
                        ->successNotificationTitle(__('general.reviewer_canceled'))
                        ->modalWidth('2xl')
                        ->mountUsing(function (Form $form, Review $record) {
                            $mailTemplate = DefaultMailTemplate::where('mailable', ReviewerCancelationMail::class)->first();
                            $form->fill([
                                'email' => $record->user->email,
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
                                        ->disabled()
                                        ->hidden(fn (Get $get) => $get('do-not-notify-cancelation'))
                                        ->dehydrated(),
                                    TextInput::make('subject')
                                        ->label(__('general.subject'))
                                        ->hidden(fn (Get $get) => $get('do-not-notify-cancelation'))
                                        ->required()
                                        ->columnSpanFull(),
                                    TinyEditor::make('message')
                                        ->label(__('general.message'))
                                        ->minHeight(300)
                                        ->profile('email')
                                        ->hidden(fn (Get $get) => $get('do-not-notify-cancelation'))
                                        ->columnSpanFull(),
                                    Checkbox::make('do-not-notify-cancelation')
                                        ->reactive()
                                        ->label(__('general.dont_send_notification'))
                                        ->columnSpanFull(),
                                ]),
                        ])
                        ->action(function (Action $action, Review $record, array $data) {
                            $record->update([
                                'status' => ReviewerStatus::CANCELED,
                            ]);

                            Log::make(
                                name: 'submission',
                                subject: $this->record,
                                description: __('general.submission_review_assign_canceled', [
                                    'submissionId' => $this->record->getKey(),
                                    'submissionName' => $this->record->getMeta('title'),
                                    'name' => $record->user->full_name,
                                ]),
                            )
                                ->by(auth()->user())
                                ->save();

                            if (! $data['do-not-notify-cancelation']) {
                                try {
                                    Mail::to($record->user->email)
                                        ->send(
                                            (new ReviewerCancelationMail($record))
                                                ->subjectUsing($data['subject'])
                                                ->contentUsing($data['message'])
                                        );
                                } catch (\Exception $e) {
                                    $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                                    $action->failure();
                                }
                            }

                            $action->success();
                        }),
                    Action::make('reinstate-reviewer')
                        ->color('primary')
                        ->authorize(fn () => auth()->user()->can('reinstateReviewer', $this->record))
                        ->modalWidth('2xl')
                        ->icon('iconpark-deletethree-o')
                        ->hidden(
                            fn (Review $record) => $record->status != ReviewerStatus::CANCELED
                        )
                        ->label(__('general.reinstate_reviewer'))
                        ->successNotificationTitle(__('general.reviewer_reinstated'))
                        ->form([
                            Checkbox::make('do-not-notify-reinstatement')
                                ->label(__('general.dont_send_notification'))
                                ->columnSpanFull(),
                            TinyEditor::make('message')
                                ->label(__('general.message'))
                                ->minHeight(300)
                                ->profile('email')
                                ->columnSpanFull(),
                        ])
                        ->action(function (Action $action, Review $record) {
                            $record->update([
                                'status' => ReviewerStatus::PENDING,
                            ]);
                            $action->success();
                        }),
                    Impersonate::make()
                        ->grouped()
                        ->hidden(fn (Model $record) => in_array($record->status, [ReviewerStatus::DECLINED, ReviewerStatus::CANCELED]))
                        ->visible(
                            fn (Model $record): bool => $record->user->email !== auth()->user()->email && auth()->user()->canImpersonate()
                        )
                        ->label(__('general.login_as'))
                        ->icon('iconpark-login')
                        ->color('primary')
                        ->redirectTo(SubmissionResource::getUrl('review', ['record' => $this->record]))
                        ->action(function (Model $record, Impersonate $action) {
                            $user = User::where('email', $record->user->email)->first();
                            if (! $user) {
                                $action->failureNotificationTitle(__('general.user_not_found'));
                                $action->failure();
                            }
                            if (! $action->impersonate($user)) {
                                $action->failureNotificationTitle(__('general.user_cant_impersonated'));
                                $action->failure();
                            }
                        }),
                ])
                    ->hidden(fn () => ! $this->isSelectedRoundActive()),
            ])
            ->heading(__('general.reviewers'))
            ->headerActions([
                Action::make('add-reviewer')
                    ->mountUsing(function (Form $form): void {
                        $mailTemplate = DefaultMailTemplate::where('mailable', ReviewerInvitationMail::class)->first();
                        $defaultFileIds = collect($this->selectedRound?->default_file_ids ?? [])
                            ->filter(fn ($id) => is_numeric($id))
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all();

                        $form->fill([
                            'subject' => $mailTemplate ? $mailTemplate->subject : '',
                            'message' => $mailTemplate ? $mailTemplate->html_template : '',
                            'papers' => $defaultFileIds,
                            'meta' => [
                                'response_due_date' => now()->addDays(app()->getCurrentScheduledConference()->getMeta('review_invitation_response_deadline') ?? 28)->format('d F Y'),
                                'review_due_date' => now()->addDays(app()->getCurrentScheduledConference()->getMeta('review_completion_deadline') ?? 28)->format('d F Y'),
                                'review_mode' => app()->getCurrentScheduledConference()->getMeta('review_mode'),
                                'open_review_for_author' => app()->getCurrentScheduledConference()->getMeta('default_open_review_for_author'),
                            ],
                        ]);
                    })
                    ->icon('iconpark-adduser-o')
                    ->outlined()
                    ->label(__('general.reviewer'))
                    ->modalHeading(__('general.assign_reviewer'))
                    ->modalWidth('2xl')
                    ->authorize(fn () => auth()->user()->can('assignReviewer', $this->record))
                    ->hidden(fn (): bool => $this->selectedRound && ! $this->selectedRound->isOpen())
                    ->form(fn ($form) => $this->form($form))
                    ->action(function (Action $action, array $data) {
                        $reviewRound = $this->ensureSelectedOpenRound();

                        if (Review::query()
                            ->where('review_round_id', $reviewRound->getKey())
                            ->where('user_id', $data['user_id'])
                            ->exists()) {
                            $action->failureNotificationTitle(__('general.reviewer_already_assigned'));
                            $action->failure();

                            return;
                        }

                        $reviewAssignment = $this->record->reviews()
                            ->create([
                                'review_round_id' => $reviewRound->getKey(),
                                'user_id' => $data['user_id'],
                                'date_assigned' => now(),
                            ]);

                        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                            $reviewAssignment->setManyMeta($data['meta']);
                        }

                        $selectedFileIds = collect($data['papers'] ?? ($reviewRound->default_file_ids ?? []))
                            ->filter(fn ($id) => is_numeric($id))
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values();

                        if ($selectedFileIds->isNotEmpty()) {
                            foreach ($selectedFileIds as $submissionFileId) {
                                $submissionFile = SubmissionFile::query()
                                    ->where('submission_id', $this->record->getKey())
                                    ->whereIn('category', [SubmissionFileCategory::PAPER_FILES, SubmissionFileCategory::REVISION_FILES])
                                    ->find($submissionFileId);

                                if (! $submissionFile) {
                                    continue;
                                }

                                $reviewAssignment->assignedFiles()
                                    ->create([
                                        'submission_file_id' => $submissionFile->getKey(),
                                    ]);
                            }
                        }

                        Log::make(
                            name: 'submission',
                            subject: $this->record,
                            description: __('general.submission_review_assigned', [
                                'submissionId' => $this->record->getKey(),
                                'submissionName' => $this->record->getMeta('title'),
                                'name' => $reviewAssignment->user->full_name,
                            ]),
                        )
                            ->by(auth()->user())
                            ->save();

                        if (! $data['no-invitation-notification']) {
                            try {
                                Mail::to($reviewAssignment->user->email)
                                    ->send(
                                        (new ReviewerInvitationMail($reviewAssignment))
                                            ->subjectUsing($data['subject'])
                                            ->contentUsing($data['message'])
                                    );
                            } catch (\Exception $e) {
                                $action->failureNotificationTitle(__('general.email_notification_was_not_delivered'));
                                $action->failure();
                            }
                        }
                    }),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.reviewer-list');
    }
}
