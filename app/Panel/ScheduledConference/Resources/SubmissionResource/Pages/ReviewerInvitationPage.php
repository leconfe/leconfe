<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Constants\ReviewerStatus;
use App\Facades\Setting;
use App\Infolists\Components\LivewireEntry;
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
        $this->review = $this->record->reviews()->where('user_id', auth()->id())->first() ?? null;

        if (! $this->review) {
            abort(404);
        }
    }

    public function getHeading(): string|Htmlable
    {
        return 'Reviewer Request: '.$this->record->getMeta('title');
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
            ->label('Accept Request')
            ->icon('lineawesome-check-circle-solid')
            ->visible(
                fn (): bool => $this->review->status == ReviewerStatus::PENDING
            )
            ->color('primary')
            ->outlined()
            ->requiresConfirmation()
            ->successNotificationTitle('Request Accepted')
            ->action(function (Action $action) {

                try {
                    DB::beginTransaction();

                    $this->review->update([
                        'date_confirmed' => now(),
                        'status' => ReviewerStatus::ACCEPTED,
                    ]);

                    $editorsId = $this->record
                        ->editors()
                        ->pluck('user_id')
                        ->toArray();

                    $editors = User::whereIn('id', $editorsId)->get();
                    if ($editors->count()) {
                        try {
                            Mail::to($editors)
                                ->send(
                                    new ReviewerAcceptedInvitationMail($this->review)
                                );
                        } catch (\Exception $e) {
                            $action->failureNotificationTitle('Failed to send notification to author');
                            $action->failure();
                        }
                    }

                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();

                    $action->failure();

                    return;
                }

                $action->success();
                $action->redirect(SubmissionResource::getUrl('review', ['record' => $this->record->id]));
            });
    }

    public function declineAction()
    {
        return Action::make('declineAction')
            ->label('Decline Request')
            ->icon('lineawesome-times-circle-solid')
            ->visible(
                fn (): bool => $this->review->status == ReviewerStatus::PENDING
            )
            ->outlined()
            ->color('danger')
            ->requiresConfirmation()
            ->successNotificationTitle('Request Declined')
            ->action(function (Action $action) {
                $this->review->update([
                    'date_confirmed' => now(),
                    'status' => ReviewerStatus::DECLINED,
                ]);

                try {
                    Mail::to($this->review->user->email)
                        ->send(
                            new ReviewerDeclinedInvitationMail($this->review)
                        );
                } catch (\Exception $e) {
                    $action->failureNotificationTitle('Failed to send notification to author');
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
                    ->heading('Request for review')
                    ->description('You have been selected as a potential reviewer of the following submission. Below is an overview of the submission, as well as the timeline for this review. We hope that you are able to participate')
                    ->schema([
                        Fieldset::make('Submission Details')
                            ->columns(1)
                            ->schema([
                                TextEntry::make('Title')
                                    ->color('gray')
                                    ->getStateUsing(fn (Submission $submission) => $submission->getMeta('title')),
                                TextEntry::make('Author')
                                    ->color('gray')
                                    ->visible(fn () => in_array($this->review->getMeta('review_mode'), [Review::MODE_ANONYMOUS, Review::MODE_OPEN]))
                                    ->getStateUsing(fn (Submission $submission) => $submission->user?->fullName),
                                TextEntry::make('Keywords')
                                    ->color('gray')
                                    ->getStateUsing(function (Submission $submission) {
                                        return $submission->tagsWithType('submissionKeywords')->pluck('name')->join(', ') ?: '-';
                                    }),
                                TextEntry::make('Abstract')
                                    ->color('gray')
                                    ->html()
                                    ->getStateUsing(fn (Submission $submission) => $submission->getMeta('abstract')),
                                TextEntry::make('Review Mode')
                                    ->color('gray')
                                    ->getStateUsing(fn () => $this->review?->review_mode),
                            ]),
                        LivewireEntry::make('review-files')
                            ->livewire(ReviewerAssignedFiles::class, [
                                'record' => $this->review,
                            ]),
                        Fieldset::make('Review Schedule')
                            ->columns([
                                'default' => 1,
                                'sm' => 2,
                                'xl' => 3,
                            ])
                            ->schema([
                                TextEntry::make("Editor's Request")
                                    ->getStateUsing(
                                        fn (): ?string => $this->review?->date_assigned?->format(Setting::get('format_date'))
                                    ),
                                TextEntry::make('Response Due Date')
                                    ->getStateUsing(
                                        fn (): string => Carbon::parse($this->review?->getMeta('response_due_date'))?->format(Setting::get('format_date'))
                                    ),
                                TextEntry::make('Review Due Date')
                                    ->getStateUsing(
                                        fn (): string => Carbon::parse($this->review?->getMeta('review_due_date'))?->format(Setting::get('format_date'))
                                    ),
                            ]),
                    ]),
            ]);
    }
}
