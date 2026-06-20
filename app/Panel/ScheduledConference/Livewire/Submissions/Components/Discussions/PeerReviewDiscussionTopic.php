<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Discussions;

use App\Actions\Submissions\CreateDiscussionTopic;
use App\Actions\Submissions\UpdateDiscussionTopic;
use App\Models\DiscussionTopic;
use App\Models\Enums\SubmissionStage;
use App\Models\Participant;
use App\Models\Review;
use App\Models\Submission;
use App\Models\SubmissionParticipant;
use App\Models\User;
use App\Notifications\NewDiscussionTopic;
use Awcodes\FilamentBadgeableColumn\Components\Badge;
use Awcodes\FilamentBadgeableColumn\Components\BadgeableColumn;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Livewire;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

class PeerReviewDiscussionTopic extends \Livewire\Component implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    public Submission $submission;

    public SubmissionStage $stage;

    public ?int $reviewRoundId = null;

    public function mount(Submission $submission, SubmissionStage $stage): void
    {
        $this->submission = $submission;
        $this->stage = $stage;
        $this->reviewRoundId = $submission->activeReviewRound?->getKey()
            ?? $submission->latestReviewRound?->getKey();
    }

    #[On('peer-review-round-selected')]
    public function onReviewRoundSelected(int $roundId): void
    {
        $this->reviewRoundId = $roundId;
        $this->resetTable();
    }

    protected function isSelectedRoundOpen(): bool
    {
        if (! $this->reviewRoundId) {
            return false;
        }

        $activeRoundId = $this->submission->reviewRounds()
            ->open()
            ->orderByDesc('round_number')
            ->value('id');

        return $activeRoundId && (int) $activeRoundId === $this->reviewRoundId;
    }

    protected function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label(__('general.topic_name'))
                    ->placeholder(__('general.topic_name'))
                    ->required(),
                CheckboxList::make('user_id')
                    ->label(__('general.participants'))
                    ->default([Auth::id()])
                    ->rules('required|array|min:2')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) {
                            $reviewUserIds = $this->submission->reviews()
                                ->where('review_round_id', $this->reviewRoundId ?: 0)
                                ->pluck('user_id');
                            $participantUserIds = $this->submission->participants->pluck('user_id');
                            $users = User::query()
                                ->with(['roles'])
                                ->whereIn('id', $value)
                                ->lazy();

                            $participantsToConsider = $blindReviewerCount = 0;

                            foreach ($users as $user) {
                                // if participant has no role in this stage and is not a reviewer
                                if (! $participantUserIds->contains($user->getKey()) && ! $reviewUserIds->contains($user->getKey())) {
                                    // ignore user, if participant is current user and the user can view without being an assigned user
                                    if ($user->is(auth()->user()) && $user->can('Submission:view')) {
                                        continue;
                                    } else {
                                        $fail(__('general.discussion_not_submission_participant'));
                                    }
                                }

                                $blindReviewer = false;
                                // is participant a blind reviewer
                                $review = $this->submission->getReviewForUserInActiveRound($user);
                                if ($review && $review->getMeta('review_mode') !== Review::MODE_OPEN) {
                                    $blindReviewer = true;
                                    $blindReviewerCount++;
                                }

                                // if participant is not a blind reviewer and has a role different than editor or assistant
                                if (! $blindReviewer && ! $user->can('actAsEditor', $this->submission)) {
                                    $participantsToConsider++;
                                }

                                // if anonymity is impacted, display error
                                if (($blindReviewerCount > 1) || ($blindReviewerCount > 0 && $participantsToConsider > 0)) {
                                    $fail(__('general.discussion_error_anonymous_review'));
                                    break;
                                }
                            }
                        },
                    ])
                    ->options(function () {

                        $this->submission->load([
                            'reviews' => fn ($query) => $query
                                ->with(['meta', 'user.meta'])
                                ->where('review_round_id', $this->reviewRoundId ?: 0),
                            'participants' => ['user.meta', 'role'],
                        ]);

                        $users = collect();
                        $participantUsers = $this->submission->participants
                            ->filter(function (SubmissionParticipant $participant) {

                                $review = $this->submission->getReviewForUserInActiveRound(Auth::user());

                                if ($review && $review->getMeta('review_mode') != Review::MODE_OPEN && $this->submission->isAuthor($participant->user)) {
                                    return false;
                                }

                                return true;
                            })
                            ->mapWithKeys(fn ($participant) => [$participant->user->getKey() => $participant->user->fullName.' ('.$participant->role->name.')']);

                        $users = $users->union($participantUsers);

                        $reviewUsers = $this->submission->reviews
                            ->when($this->submission->isAuthor(Auth::user()) || $this->submission->getReviewForUserInActiveRound(Auth::user()), fn ($reviews) => $reviews->filter(fn ($review) => $review->user->is(Auth::user()) ?: $review->getMeta('review_mode') == Review::MODE_OPEN))
                            ->mapWithKeys(fn (Review $review) => [$review->user->getKey() => $review->user->fullName.' ('.$review->reviewMode.')']);

                        $users = $users->union($reviewUsers);

                        if (! isset($users[Auth::id()])) {
                            $users[Auth::id()] = Auth::user()->fullName.' (Unassigned)';
                        }

                        return $users;
                    }),
            ]);
    }

    public function getEloquentQuery()
    {
        return DiscussionTopic::query()
            ->with(['discussions.user'])
            ->where('submission_id', $this->submission->getKey())
            ->where('stage', $this->stage)
            ->where('review_round_id', $this->reviewRoundId ?: 0)
            ->when(
                ! auth()->user()->can('actAsEditor', $this->submission),
                fn ($query) => $query->whereHas('participants', fn ($query) => $query->where('user_id', auth()->user()->getKey()))
            );
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('general.discussion'))
            ->query(fn () => $this->getEloquentQuery())
            ->recordAction('open-discussion-detail')
            ->actions([
                ActionGroup::make([
                    Action::make('open-discussion-detail')
                        ->icon('lineawesome-eye-solid')
                        ->label(__('general.details'))
                        ->modalWidth('6xl')
                        ->modalHeading(fn (Model $discussionTopic): string => __('general.discussion_for_topic', ['variable' => $discussionTopic->name]))
                        ->modalSubmitAction(false)
                        ->infolist(function (Model $discussionTopic) {
                            return [
                                Livewire::make(
                                    DiscussionDetail::class,
                                    ['topic' => $discussionTopic]
                                )->lazy(),
                                Fieldset::make('form-discussion-detail')
                                    ->label(__('general.add_message'))
                                    ->columns(1)
                                    ->visible(fn ($record): bool => $record->open && $this->isSelectedRoundOpen())
                                    ->schema([
                                        Livewire::make(
                                            DiscussionDetailForm::class,
                                            ['topic' => $discussionTopic]
                                        )->lazy(),
                                    ]),
                            ];
                        }),
                    Action::make('update-topic')
                        ->label(__('general.edit'))
                        ->icon('lineawesome-edit-solid')
                        ->hidden(fn (): bool => ! $this->isSelectedRoundOpen())
                        ->mountUsing(function ($record, Form $form) {
                            $form->fill([
                                'name' => $record->name,
                                'user_id' => $record->participants()->pluck('user_id')->toArray(),
                            ]);
                        })
                        ->authorize(fn ($record) => auth()->user()->can('update', $record))
                        ->form(fn (Form $form) => $this->form($form))
                        ->successNotificationTitle(__('general.topic_updated_successfully'))
                        ->action(function (Action $action, array $data, Model $record) {
                            UpdateDiscussionTopic::run(
                                $record,
                                ['name' => $data['name']],
                                $data['user_id']
                            );
                            $action->success();
                        }),
                    Action::make('close')
                        ->authorize(fn ($record) => auth()->user()->can('close', $record))
                        ->hidden(fn (): bool => ! $this->isSelectedRoundOpen())
                        ->label(fn ($record): string => $record->open ? __('general.close') : __('general.open'))
                        ->color(fn ($record): string => $record->open ? 'warning' : 'success')
                        ->icon(fn ($record): string => $record->open ? 'lineawesome-lock-solid' : 'lineawesome-unlock-solid')
                        ->requiresConfirmation()
                        ->successNotificationTitle(__('general.topic_updated_successfully'))
                        ->action(function (Action $action, $record) {
                            $record->update(['open' => ! $record->open]);
                            $action->success();
                        }),
                    DeleteAction::make()
                        ->hidden(fn (): bool => ! $this->isSelectedRoundOpen())
                        ->authorize('DiscussionTopic:delete'),
                ]),
            ])
            ->headerActions([
                Action::make('create-topic')
                    ->authorize('create', DiscussionTopic::class)
                    ->hidden(fn (): bool => ! $this->isSelectedRoundOpen())
                    ->icon('lineawesome-plus-solid')
                    ->outlined()
                    ->label(__('general.topic'))
                    ->modalWidth('xl')
                    ->form(fn ($form) => $this->form($form))
                    ->successNotificationTitle(__('general.topic_created_successfully'))
                    ->failureNotificationTitle(__('general.topic_createtion_failed'))
                    ->action(function (Action $action, array $data, Form $form) {
                        $form->validate();

                        $topic = CreateDiscussionTopic::run(
                            $this->submission,
                            [
                                'name' => $data['name'],
                                'stage' => $this->stage,
                                'review_round_id' => $this->reviewRoundId,
                            ],
                            $data['user_id']
                        );

                        try {
                            $topic->participants()
                                ->with('user')
                                ->get()
                                ->each(function ($participant) use ($topic) {
                                    $participant->user->notify(
                                        new NewDiscussionTopic($topic)
                                    );
                                });
                        } catch (\Throwable $th) {
                            $action->failureNotificationTitle(__('general.failed_to_send_notification_to_participants'));
                            $action->failure();
                        } finally {
                            $action->success();
                        }
                    }),
            ])
            ->columns([
                BadgeableColumn::make('name')
                    ->label(__('general.name'))
                    ->wrap()
                    ->suffixBadges([
                        Badge::make('status')
                            ->label(fn ($record) => $record->open ? __('general.open') : __('general.closed'))
                            ->color(fn ($record) => $record->open ? 'success' : 'danger'),
                    ]),
                TextColumn::make('Last Update')
                    ->label(__('general.last_update'))
                    ->getStateUsing(fn ($record) => $record->getLastSender()?->fullName ?? '-')
                    ->description(fn ($record): ?string => $record->getLastUpdate()),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.components.discussions.discussion-topic');
    }
}
