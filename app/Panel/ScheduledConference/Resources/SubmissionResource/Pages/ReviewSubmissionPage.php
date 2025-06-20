<?php

namespace App\Panel\ScheduledConference\Resources\SubmissionResource\Pages;

use App\Actions\Review\ReviewUpdateAction;
use App\Classes\Log;
use App\Constants\ReviewerStatus;
use App\Constants\SubmissionStatusRecommendation;
use App\Facades\Hook;
use App\Forms\Components\TinyEditor;
use App\Mail\Templates\ReviewCompleteMail;
use App\Models\Review;
use App\Models\ReviewFormItem;
use App\Models\Submission;
use App\Models\User;
use App\Panel\ScheduledConference\Resources\SubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReviewSubmissionPage extends Page implements HasActions, HasInfolists
{
    use InteractsWithActions, InteractsWithInfolists;

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.review-submission-page';

    public Submission $record;

    public ?Review $review;

    public array $formData = [];

    public ?string $recommendation = null;

    public function mount()
    {
        abort_unless(auth()->user()->can('review', $this->record), 403);

        $this->review = $this->record->reviews
            ->where('user_id', auth()->user()->getKey())
            ->first() ?? null;

        abort_if($this->review->status == ReviewerStatus::DECLINED, 403, 'You have declined this review request');
        abort_if($this->review->status == ReviewerStatus::CANCELED, 403, 'This review request has been canceled');

        if ($this->review->status == ReviewerStatus::PENDING) {
            redirect(SubmissionResource::getUrl('reviewer-invitation', ['record' => $this->record]));
        }

        $formData = [
            ...$this->review->attributesToArray(),
            'meta' => $this->review->getAllMeta(),
        ];

        Hook::call('ReviewSubmissionPage::Form::fill', [&$formData, $this]);

        $this->form->fill($formData);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Review: '.$this->record->getMeta('title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                InfolistSection::make()
                    ->heading('Submission Details')
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('Title')
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('title')
                                    ),
                                TextEntry::make('Author')
                                    ->color('gray')
                                    ->visible(fn () => in_array($this->review?->getMeta('review_mode'), [Review::MODE_ANONYMOUS, Review::MODE_OPEN]))
                                    ->getStateUsing(fn (Submission $submission) => $submission->user?->fullName),
                                TextEntry::make('Keywords')
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->tagsWithType('submissionKeywords')->pluck('name')->join(', ') ?: '-'
                                    ),
                                TextEntry::make('Abstract')
                                    ->color('gray')
                                    ->html()
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('abstract')
                                    ),
                                TextEntry::make('Review Mode')
                                    ->color('gray')
                                    ->getStateUsing(fn () => $this->review?->review_mode),
                            ]),
                    ]),
            ]);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('View Guidelines')
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->action(
                    fn () => $this->dispatch('show-guidelines')
                ),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->id('reviewSubmissionForm')
            ->model($this->review)
            ->statePath('formData')
            ->disabled(fn () => $this->review->reviewSubmitted())
            ->schema([
                Section::make()
                    ->heading('Review Form')
                    ->schema([
                        TinyEditor::make('meta.review_for_author_editor')
                            ->minHeight(300)
                            ->label('Review for Author and Editor'),
                        TinyEditor::make('meta.review_for_editor')
                            ->minHeight(300)
                            ->label('Review for Editor'),
                        Select::make('recommendation')
                            ->required()
                            ->options(SubmissionStatusRecommendation::list()),
                        ...ReviewFormItem::query()->lazy()->map(fn(ReviewFormItem $item) => $item->getFormField())->toArray(),
                    ]),
            ]);
    }

    public function submitReviewAction()
    {
        return Action::make('submitReviewAction')
            ->icon('lineawesome-check-circle-solid')
            ->label('Submit Review')
            ->requiresConfirmation()
            ->hidden(fn () => $this->review->reviewSubmitted())
            ->successNotificationTitle('Review submitted successfully')
            ->action(function (Action $action) {
                $data = $this->form->getState();
                $data['date_completed'] = now();
                $data['score'] = $this->review->calculateReviewScore($data['meta']['review_responses']);
                
                try {
                    DB::beginTransaction();

                    ReviewUpdateAction::run($this->review, $data);

                    Log::make(
                        name: 'submission',
                        subject: $this->record,
                        description: __('general.submission_review_completed', [
                            'name' => $this->review->user->full_name,
                        ]),
                    )
                        ->by(auth()->user())
                        ->save();

                    $editors = $this->record->editors()
                        ->pluck('user_id')
                        ->toArray();

                    $editors = User::whereIn('id', $editors)->get();

                    if ($editors->count()) {
                        Mail::to($editors)->send(
                            new ReviewCompleteMail($this->review)
                        );
                    }

                    $action->success();

                    DB::commit();
                } catch (\Throwable $th) {
                    DB::rollBack();

                    $action->failureNotificationTitle($th->getMessage());
                    $action->failure();

                    return;
                }

            });
    }

    public function saveForLaterAction()
    {
        return Action::make('saveForLaterAction')
            ->label('Save for Later')
            ->outlined()
            ->hidden(fn () => $this->review->reviewSubmitted())
            ->successNotificationTitle('Review saved')
            ->action(function (Action $action) {
                $data = $this->formData;

                try {
                    ReviewUpdateAction::run($this->review, $data);
                } catch (\Throwable $th) {
                    $action->failureNotificationTitle($th->getMessage());
                    $action->failure();

                    throw $th;
                }

                $action->success();
            });
    }
}
