<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components;

use App\Constants\ReviewerStatus;
use App\Constants\SubmissionFileCategory;
use App\Constants\SubmissionStatusRecommendation;
use App\Forms\Components\TinyEditor;
use App\Infolists\Components\LivewireEntry;
use App\Mail\Templates\ReviewerCancelationMail;
use App\Mail\Templates\ReviewerInvitationMail;
use App\Models\DefaultMailTemplate;
use App\Models\Enums\SubmissionStatus;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\ReviewerAssignedFile;
use App\Models\Role;
use App\Models\Submission;
use App\Models\SubmissionFile;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;

class ReviewerList extends Component implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    public Submission $record;

    public Role $reviewerRole;

    public function mount(Submission $record)
    {
        $this->reviewerRole = Role::where('name', UserRole::Reviewer->value)->first();
    }

    private static function formReviewerSchema(ReviewerList $component, bool $editMode = false): array
    {
        return [
            Select::make('user_id')
                ->label(__('general.reviewer'))
                ->placeholder(__('general.select_reviewer'))
                ->allowHtml()
                ->hidden(fn ($record) => $record)
                ->preload()
                ->required()
                ->searchable()
                ->options(function ($state) use ($component, $editMode): array {
                    return User::with('roles')
                        ->whereHas('roles', function (Builder $query) use ($component) {
                            $query->where('name', $component->reviewerRole->name);
                        })
                        ->when($editMode, function ($query) use ($component, $state) {
                            $query->whereNotIn(
                                'id',
                                $component->record->reviews()
                                    ->where('user_id', '!=', $state)
                                    ->get()
                                    ->pluck('user_id')
                                    ->toArray()
                            );
                        })
                        ->when(! $editMode, function ($query) use ($component) {
                            $query->whereNotIn(
                                'id',
                                $component->record->reviews()
                                    ->get()
                                    ->pluck('user_id')
                                    ->toArray()
                            );
                        })
                        ->get()
                        ->mapWithKeys(function (User $user) {
                            return [$user->getKey() => static::renderSelectParticipant($user)];
                        })
                        ->toArray();
                })
                ->getSearchResultsUsing(function (Get $get, string $search) use ($component) {
                    return User::with('roles')
                        ->whereHas(
                            'roles',
                            fn (Builder $query) => $query->whereName(UserRole::Reviewer->value)
                        )
                        ->whereNotIn('id', $component->record->reviews->pluck('user_id'))
                        ->where(function (Builder $query) use ($search) {
                            $query->where('given_name', 'like', "%{$search}%")
                                ->orWhere('family_name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        })
                        ->limit(10)
                        ->get()
                        ->lazy()
                        ->mapWithKeys(
                            fn (User $user) => [
                                $user->getKey() => static::renderSelectParticipant($user),
                            ]
                        )
                        ->toArray();
                }),
            CheckboxList::make('papers')
                ->label(__('general.files_be_to_reviewer'))
                ->hidden(
                    ! $component->record->getMedia(SubmissionFileCategory::PAPER_FILES)->count()
                )
                ->options(function () use ($component) {
                    return $component->record
                        ->submissionFiles()
                        ->with(['media'])
                        ->where('category', SubmissionFileCategory::PAPER_FILES)
                        ->get()
                        ->mapWithKeys(function (SubmissionFile $paper) {
                            return [
                                $paper->getKey() => new HtmlString(
                                    Action::make($paper->media->file_name)
                                        ->label($paper->media->file_name)
                                        ->url(fn () => $paper->media->getTemporaryUrl(now()->addMinutes(5)))
                                        ->link()
                                        ->toHtml()
                                ),
                            ];
                        });
                })
                ->descriptions(function () use ($component) {
                    return $component->record
                        ->submissionFiles()
                        ->where('category', SubmissionFileCategory::PAPER_FILES)
                        ->get()
                        ->mapWithKeys(function (SubmissionFile $paper) {
                            return [$paper->getKey() => $paper->type->name];
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
                ->options(Review::getModeOptions()),
        ];
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
                    ->when(
                        auth()->user()->is($this->record->user),
                        fn ($query) => $query->whereMeta('review_mode', Review::MODE_OPEN)
                            ->where('status', ReviewerStatus::ACCEPTED)
                    )
            )
            ->columns([
                Split::make([
                    SpatieMediaLibraryImageColumn::make('user.profile')
                        ->label(__('general.profile'))
                        ->grow(false)
                        ->collection('profile')
                        ->conversion('avatar')
                        ->width(50)
                        ->height(50)
                        ->defaultImageUrl(
                            fn (Review $record): string => $record->user->getFilamentAvatarUrl()
                        )
                        ->extraCellAttributes([
                            'style' => 'width: 1px',
                        ])
                        ->circular(),
                    Stack::make([
                        TextColumn::make('user.fullName')
                            ->label(__('general.full_name'))
                            ->color(
                                fn (Review $record): string => $record->status == ReviewerStatus::CANCELED ? 'danger' : 'primary'
                            )
                            ->description(
                                fn (Review $record): string => $record->user->email
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
                        ->formatStateUsing(function ($state) {
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
                Action::make('see-reviews')
                    ->hidden(
                        //No review is need to be seen.
                        fn (Review $record): bool => is_null($record->date_completed)
                    )
                    ->modalWidth('2xl')
                    ->modalCancelActionLabel(__('general.close'))
                    ->modalSubmitAction(false)
                    ->icon('lineawesome-eye')
                    ->infolist(function (Review $record): array {
                        return [
                            TextEntry::make('Reviewer')
                                ->label(__('general.reviewer'))
                                ->size('base')
                                ->getStateUsing(fn (): string => $record->user->fullName.' ('.$record->user->email.')')
                                ->weight(FontWeight::Bold),
                            TextEntry::make('Recommendation')
                                ->label(__('general.recommendation'))
                                ->size('base')
                                ->badge()
                                ->color(
                                    fn (): string => match ($record->recommendation) {
                                        SubmissionStatusRecommendation::ACCEPT => 'primary',
                                        SubmissionStatusRecommendation::DECLINE => 'danger',
                                        default => 'warning'
                                    }
                                )
                                ->getStateUsing(fn (): string => $record->recommendation),
                            TextEntry::make('Review for Author and Editor')
                                ->label(__('general.review_for_author_and_editor'))
                                ->size('base')
                                ->color('gray')
                                ->html()
                                ->getStateUsing(fn (): ?string => $record->review_author_editor ?? '-'),
                            TextEntry::make('Review for Editor')
                                ->label(__('general.review_for_editor'))
                                ->hidden(
                                    fn (): bool => $this->record->user->getKey() == auth()->id()
                                )
                                ->size('base')
                                ->color('gray')
                                ->html()
                                ->getStateUsing(fn (): ?string => $record->review_editor ?? '-'),
                            LivewireEntry::make('reviewer-files')
                                ->livewire(ReviewerFiles::class, [
                                    'record' => $record,
                                ])
                                ->lazy(),
                        ];
                    }),
                ActionGroup::make([
                    Action::make('edit-reviewer')
                        ->hidden(fn (Model $record) => in_array($record->status, [ReviewerStatus::DECLINED, ReviewerStatus::CANCELED]))
                        ->visible(
                            fn ($record): bool => $this->record->status == SubmissionStatus::OnReview && ! $record->recommendation
                        )
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
                        ->form(static::formReviewerSchema($this, true))
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
                        ->successNotificationTitle(__('general.email_sent'))
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
                ]),
            ])
            ->heading(__('general.reviewers'))
            ->headerActions([
                Action::make('add-reviewer')
                    ->mountUsing(function (Form $form): void {
                        $mailTemplate = DefaultMailTemplate::where('mailable', ReviewerInvitationMail::class)->first();

                        $form->fill([
                            'subject' => $mailTemplate ? $mailTemplate->subject : '',
                            'message' => $mailTemplate ? $mailTemplate->html_template : '',
                            'meta' => [
                                'response_due_date' => now()->addDays(app()->getCurrentScheduledConference()->getMeta('review_invitation_response_deadline') ?? 28)->format('d F Y'),
                                'review_due_date' => now()->addDays(app()->getCurrentScheduledConference()->getMeta('review_completion_deadline') ?? 28)->format('d F Y'),
                                'review_mode' => app()->getCurrentScheduledConference()->getMeta('review_mode'),
                            ],
                        ]);
                    })
                    ->icon('iconpark-adduser-o')
                    ->outlined()
                    ->label(__('general.reviewer'))
                    ->modalHeading(__('general.assign_reviewer'))
                    ->modalWidth('2xl')
                    ->authorize(fn () => auth()->user()->can('assignReviewer', $this->record))
                    ->form(static::formReviewerSchema($this))
                    ->action(function (Action $action, array $data) {
                        if ($this->record->reviews()->where('user_id', $data['user_id'])->exists()) {
                            $action->failureNotificationTitle(__('general.reviewer_already_assigned'));
                            $action->failure();

                            return;
                        }

                        $reviewAssignment = $this->record->reviews()
                            ->create([
                                'user_id' => $data['user_id'],
                                'date_assigned' => now(),
                            ]);

                        if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                            $reviewAssignment->setManyMeta($data['meta']);
                        }

                        if (isset($data['papers'])) {
                            foreach ($data['papers'] as $submissionFileId) {
                                $submissionFile = SubmissionFile::find($submissionFileId);
                                $reviewAssignment->assignedFiles()
                                    ->create([
                                        'submission_file_id' => $submissionFile->getKey(),
                                    ]);
                            }
                        }

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
