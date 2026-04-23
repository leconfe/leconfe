<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Review\ReviewUpdateAction;
use App\Classes\Log;
use App\Constants\ReviewerStatus;
use App\Facades\Setting;
use App\Mail\Templates\ReviewerAcceptedInvitationMail;
use App\Mail\Templates\ReviewerDeclinedInvitationMail;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use App\Panel\ScheduledConference\Livewire\Submissions\Components\ReviewerAssignedFiles;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Livewire;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\View\Compilers\BladeCompiler;

class ReviewerInvitationPage extends Page implements HasActions, HasInfolists
{
    use InteractsWithActions, InteractsWithInfolists;

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.reviewer-invitation-page';

    public Submission $record;

    public ?Review $review;

    public function mount(Submission $record)
    {
        $this->record = $record;
        $this->review = $this->record->getReviewForUserInActiveRound(auth()->user());

        if (! $this->review) {
            abort(403);
        }
    }

    protected function resolveCurrentReview(): ?Review
    {
        $submission = $this->record->refresh();
        $review = $submission->getReviewForUserInActiveRound(auth()->user());

        if (! $review || ! $this->review || $review->getKey() !== $this->review->getKey()) {
            return null;
        }

        return $this->review = $review;
    }

    public function getHeading(): string|Htmlable
    {
        return __('general.reviewer_request_heading', [
            'title' => $this->record->getMeta('title'),
        ]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        if ($this->review->status == ReviewerStatus::DECLINED) {
            return new HtmlString(
                BladeCompiler::render("<x-filament::badge color='danger' class='w-fit'>".ReviewerStatus::DECLINED.'</x-filament::badge>')
            );
        }

        return null;
    }

    public function acceptAction()
    {
        return Action::make('acceptAction')
            ->label(__('general.accept_request'))
            ->icon('lineawesome-check-circle-solid')
            ->visible(
                fn (): bool => $this->review->status == ReviewerStatus::PENDING
            )
            ->color('primary')
            ->outlined()
            ->requiresConfirmation()
            ->successNotificationTitle(__('general.request_accepted'))
            ->failureNotificationTitle(__('general.failed_to_accept_request'))
            ->action(function (Action $action) {
                $review = $this->resolveCurrentReview();

                if (! $review) {
                    $action->failureNotificationTitle(__('general.review_request_canceled'));
                    $action->failure();

                    return;
                }

                try {
                    DB::beginTransaction();

                    ReviewUpdateAction::run($review, [
                        'date_confirmed' => now(),
                        'status' => ReviewerStatus::ACCEPTED,
                    ]);

                    Log::make(
                        name: 'submission',
                        subject: $this->record,
                        description: __('general.submission_review_assign_accepted', [
                            'submissionId' => $this->record->getKey(),
                            'submissionName' => $this->record->getMeta('title'),
                            'name' => $review->user->full_name,
                        ]),
                    )
                        ->by(auth()->user())
                        ->save();

                    $editorsId = $this->record
                        ->editors()
                        ->pluck('user_id')
                        ->toArray();

                    $editors = User::whereIn('id', $editorsId)->get();
                    if ($editors->count()) {
                        try {
                            Mail::to($editors)
                                ->send(
                                    new ReviewerAcceptedInvitationMail($review)
                                );
                        } catch (\Exception $e) {
                            $action->failureNotificationTitle(__('general.failed_send_notification_to_author'));
                            $action->failure();
                        }
                    }

                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();

                    $action->failure();

                    throw $th;

                    return;
                }

                $action->success();
                $action->redirect(SubmissionResource::getUrl('review', ['record' => $this->record->id]));
            });
    }

    public function declineAction()
    {
        return Action::make('declineAction')
            ->label(__('general.decline_request'))
            ->icon('lineawesome-times-circle-solid')
            ->visible(
                fn (): bool => $this->review->status == ReviewerStatus::PENDING
            )
            ->outlined()
            ->color('danger')
            ->requiresConfirmation()
            ->successNotificationTitle(__('general.request_declined'))
            ->action(function (Action $action) {
                $review = $this->resolveCurrentReview();

                if (! $review) {
                    $action->failureNotificationTitle(__('general.review_request_canceled'));
                    $action->failure();

                    return;
                }

                ReviewUpdateAction::run($review, [
                    'date_confirmed' => now(),
                    'status' => ReviewerStatus::DECLINED,
                ]);

                Log::make(
                    name: 'submission',
                    subject: $this->record,
                    description: __('general.submission_review_assign_declined', [
                        'submissionId' => $this->record->getKey(),
                        'submissionName' => $this->record->getMeta('title'),
                        'name' => $review->user->full_name,
                    ]),
                )
                    ->by(auth()->user())
                    ->save();

                try {
                    Mail::to($review->user->email)
                        ->send(
                            new ReviewerDeclinedInvitationMail($review)
                        );
                } catch (\Exception $e) {
                    $action->failureNotificationTitle(__('general.failed_send_notification_to_author'));
                    $action->failure();
                }

                $action->success();
            });
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Section::make()
                    ->aside()
                    ->heading(__('general.request_for_review'))
                    ->description(__('general.request_for_review_description'))
                    ->schema([
                        Fieldset::make(__('general.submission_details'))
                            ->columns(1)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('general.title'))
                                    ->color('gray')
                                    ->getStateUsing(fn (Submission $submission) => $submission->getMeta('title')),
                                TextEntry::make('author')
                                    ->label(__('general.author'))
                                    ->color('gray')
                                    ->visible(fn () => $this->review->isShowAuthor())
                                    ->getStateUsing(fn (Submission $submission) => $submission->user?->fullName),
                                TextEntry::make('keywords')
                                    ->label(__('general.keywords'))
                                    ->color('gray')
                                    ->badge()
                                    ->getStateUsing(fn (Submission $submission) => $submission->getMeta('keywords') ?: '-'),
                                TextEntry::make('abstract')
                                    ->label(__('general.abstract'))
                                    ->color('gray')
                                    ->html()
                                    ->getStateUsing(fn (Submission $submission) => $submission->getMeta('abstract')),
                                TextEntry::make('review_mode')
                                    ->label(__('general.review_mode'))
                                    ->color('gray')
                                    ->getStateUsing(fn () => $this->review?->review_mode),
                            ]),
                        Livewire::make(ReviewerAssignedFiles::class, [
                            'record' => $this->review,
                        ]),
                        Fieldset::make(__('general.review_schedule'))
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'xl' => 3,
                            ])
                            ->schema([
                                TextEntry::make('editor_request')
                                    ->label(__('general.editor_request'))
                                    ->getStateUsing(
                                        fn (): ?string => $this->review?->date_assigned?->format(Setting::get('format_date'))
                                    ),
                                TextEntry::make('response_due_date')
                                    ->label(__('general.response_due_date'))
                                    ->getStateUsing(
                                        fn (): string => Carbon::parse($this->review?->getMeta('response_due_date'))?->format(Setting::get('format_date'))
                                    ),
                                TextEntry::make('review_due_date')
                                    ->label(__('general.review_due_date'))
                                    ->getStateUsing(
                                        fn (): string => Carbon::parse($this->review?->getMeta('review_due_date'))?->format(Setting::get('format_date'))
                                    ),
                            ]),
                    ]),
            ]);
    }
}
