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

        $this->review = $this->record->getReviewForUserInActiveRound(auth()->user());

        abort_if(! $this->review, 403);

        abort_if($this->review->status == ReviewerStatus::DECLINED, 403, __('general.review_request_declined'));
        abort_if($this->review->status == ReviewerStatus::CANCELED, 403, __('general.review_request_canceled'));

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
        return __('general.review_submission_heading', [
            'title' => $this->record->getMeta('title'),
        ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                InfolistSection::make()
                    ->heading(__('general.submission_details'))
                    ->schema([
                        Grid::make(1)
                            ->schema([
                                TextEntry::make('title')
                                    ->label(__('general.title'))
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('title')
                                    ),
                                TextEntry::make('author')
                                    ->label(__('general.author'))
                                    ->color('gray')
                                    ->visible(fn () => in_array($this->review?->getMeta('review_mode'), [Review::MODE_ANONYMOUS, Review::MODE_OPEN]))
                                    ->getStateUsing(fn (Submission $submission) => $submission->user?->fullName),
                                TextEntry::make('keywords')
                                    ->label(__('general.keywords'))
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->tagsWithType('submissionKeywords')->pluck('name')->join(', ') ?: '-'
                                    ),
                                TextEntry::make('abstract')
                                    ->label(__('general.abstract'))
                                    ->color('gray')
                                    ->html()
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('abstract')
                                    ),
                                TextEntry::make('review_mode')
                                    ->label(__('general.review_mode'))
                                    ->color('gray')
                                    ->getStateUsing(fn () => $this->review?->review_mode),
                            ]),
                    ]),
            ]);
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('viewGuidelines')
                ->label(__('general.view_guidelines'))
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->hidden(fn (): bool => ! $this->hasGuidanceModalContent())
                ->action(
                    fn () => $this->dispatch('show-guidelines')
                ),
        ];
    }

    public function hasReviewGuidelines(): bool
    {
        return $this->hasHtmlContent(app()->getCurrentScheduledConference()?->getMeta('review_guidelines'));
    }

    public function hasCompetingInterests(): bool
    {
        return $this->hasHtmlContent(app()->getCurrentScheduledConference()?->getMeta('competing_interests'));
    }

    public function hasGuidanceModalContent(): bool
    {
        return $this->hasReviewGuidelines() || $this->hasCompetingInterests();
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
                    ->heading(__('scheduled_conference.review_form'))
                    ->schema([
                        ...ReviewFormItem::ordered()->lazy()->map(fn (ReviewFormItem $item) => $item->getFormField())->toArray(),
                        TinyEditor::make('meta.review_for_author_editor')
                            ->minHeight(300)
                            ->label(__('general.review_for_author_and_editor')),
                        TinyEditor::make('meta.review_for_editor')
                            ->minHeight(300)
                            ->label(__('general.review_for_editor')),
                        Select::make('recommendation')
                            ->required()
                            ->label(__('general.recommendation'))
                            ->options(SubmissionStatusRecommendation::list()),
                    ]),
            ]);
    }

    public function submitReviewAction()
    {
        return Action::make('submitReviewAction')
            ->icon('lineawesome-check-circle-solid')
            ->label(__('general.submit_review'))
            ->requiresConfirmation()
            ->hidden(fn () => $this->review->reviewSubmitted())
            ->successNotificationTitle(__('general.review_submitted_successfully'))
            ->action(function (Action $action) {
                $data = $this->form->getState();
                $data['date_completed'] = now();

                if (array_key_exists('review_responses', data_get($data, 'meta', []))) {
                    data_set($data, 'meta.review_responses', ReviewFormItem::filterOutUploadResponses(data_get($data, 'meta.review_responses')));
                }

                if (isset($data['meta']['review_responses'])) {
                    $data['score'] = $this->review->calculateReviewScore($data['meta']['review_responses'] ?? []);
                }

                try {
                    DB::beginTransaction();

                    ReviewUpdateAction::run($this->review, $data);
                    $this->form->model($this->review)->saveRelationships();

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
            ->label(__('general.save_for_later'))
            ->outlined()
            ->hidden(fn () => $this->review->reviewSubmitted())
            ->successNotificationTitle(__('general.review_saved'))
            ->action(function (Action $action) {
                $data = $this->formData;

                if (array_key_exists('review_responses', data_get($data, 'meta', []))) {
                    data_set($data, 'meta.review_responses', ReviewFormItem::filterOutUploadResponses(data_get($data, 'meta.review_responses')));
                }

                try {
                    ReviewUpdateAction::run($this->review, $data);
                    $this->form->model($this->review)->saveRelationships();
                } catch (\Throwable $th) {
                    $action->failureNotificationTitle($th->getMessage());
                    $action->failure();

                    throw $th;
                }

                $action->success();
            });
    }

    protected function hasHtmlContent(?string $value): bool
    {
        $plainText = preg_replace(
            '/\s+/u',
            '',
            strip_tags(html_entity_decode($value ?? '', ENT_QUOTES | ENT_HTML5))
        );

        return filled($plainText);
    }
}
