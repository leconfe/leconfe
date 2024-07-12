<?php

namespace App\Panel\Conference\Resources\SubmissionResource\Pages;

use App\Constants\ReviewerStatus;
use App\Constants\SubmissionStatusRecommendation;
use App\Mail\Templates\ReviewCompleteMail;
use App\Models\Enums\UserRole;
use App\Models\Review;
use App\Models\Submission;
use App\Models\User;
use App\Panel\Conference\Livewire\Workflows\Concerns\InteractWithTenant;
use App\Panel\Conference\Resources\SubmissionResource;
use Awcodes\Shout\Components\ShoutEntry;
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
use Illuminate\Support\Facades\Mail;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class ReviewSubmissionPage extends Page implements HasActions, HasInfolists
{
    use InteractsWithActions, InteractsWithInfolists, InteractWithTenant;

    protected static string $resource = SubmissionResource::class;

    protected static string $view = 'panel.conference.resources.submission-resource.pages.review-submission-page';

    public Submission $record;

    public ?Review $review;

    public array $reviewData = [];

    public ?string $recommendation = null;

    public function mount()
    {
        abort_unless(auth()->user()->can('review', $this->record), 403);

        $this->review = $this->record->reviews()
            ->user(auth()->user())
            ->first() ?? null;

        abort_if(! $this->review, 404);

        abort_if($this->review->status == ReviewerStatus::DECLINED, 403, __('translation.reviewSubmissionPage.abortIfDeclined'));
        abort_if($this->review->status == ReviewerStatus::CANCELED, 403, __('translation..abortIfCanceled'));

        if ($this->review->status == ReviewerStatus::PENDING) {
            redirect(SubmissionResource::getUrl('reviewer-invitation', ['record' => $this->record]));
        }

        $this->recommendation = $this->review->recommendation;

        $this->reviewData = [
            'review_author_editor' => $this->review->review_author_editor,
            'review_editor' => $this->review->review_editor,
        ];
    }

    public function getHeading(): string|Htmlable
    {
        return __('translation.reviewSubmissionPage.getHeadingReview').$this->record->getMeta('title');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                ShoutEntry::make('thank-you')
                    ->visible(fn (): bool => $this->review->reviewSubmitted())
                    ->type('success')
                    ->content(__('translation.reviewSubmissionPage.contentThankYouForYourTime')),
                InfolistSection::make()
                    ->heading(__('translation.reviewSubmissionPage.headingSubmissionDetails'))
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('Title')
                                    ->label(__('translation.reviewSubmissionPage.labelTitle'))
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('title')
                                    ),
                                TextEntry::make('Keywords')
                                    ->label(__('translation.reviewSubmissionPage.labelKeywords'))
                                    ->color('gray')
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->tagsWithType('submissionKeywords')->pluck('name')->join(', ')
                                    ),
                                TextEntry::make('Abstract')
                                    ->label(__('translation.reviewSubmissionPage.labelAbstract'))
                                    ->color('gray')
                                    ->html()
                                    ->columnSpanFull()
                                    ->getStateUsing(
                                        fn (Submission $record): string => $record->getMeta('abstract')
                                    ),
                            ]),
                    ]),
            ]);
    }

    public function getForms(): array
    {
        return [
            'reviewForm',
            'recommendationForm',
        ];
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make(__('translation.reviewSubmissionPage.actionViewGuidelines'))
                ->icon('heroicon-o-information-circle')
                ->color('info')
                ->action(
                    fn () => $this->dispatch('show-guidelines')
                ),
        ];
    }

    public function recommendationForm(Form $form): Form
    {
        return $form
            // ->disabled(fn (): bool => $this->review->reviewSubmitted())
            ->schema([
                Section::make()
                    ->heading(__('translation.reviewSubmissionPage.headingRecommendation'))
                    ->schema([
                        Select::make('recommendation')
                            ->required()
                            ->label('')
                            ->searchable()
                            ->options(SubmissionStatusRecommendation::list()),
                    ]),
            ]);
    }

    public function reviewForm(Form $form): Form
    {
        return $form
            ->disabled(
                fn (): bool => $this->review->reviewSubmitted()
            )
            ->schema([
                Section::make()
                    ->heading(__('translation.reviewSubmissionPage.headingReviewForm'))
                    ->schema([
                        TinyEditor::make('reviewData.review_author_editor')
                            ->minHeight(300)
                            ->label(__('translation.reviewSubmissionPage.labelReviewForAuthorAndEditor')),
                        TinyEditor::make('reviewData.review_editor')
                            ->minHeight(300)
                            ->label(__('translation.reviewSubmissionPage.labelReviewForEditor')),

                    ]),
            ]);
    }

    private function validateAllForms()
    {
        foreach ($this->getForms() as $form) {
            $this->{$form}->validate();
        }
    }

    public function reviewAction()
    {
        return Action::make('reviewAction')
            ->requiresConfirmation()
            ->icon('lineawesome-check-circle-solid')
            ->extraAttributes(['class' => 'w-full'], true)
            ->outlined()
            ->color(
                fn (): string => ! is_null($this->review->recommendation) ? 'gray' : 'primary'
            )
            ->label(
                fn (): string => ! is_null($this->review->recommendation) ? __('translation.reviewSubmissionPage.labelReviewSubmitted') : __('translation.reviewSubmissionPage.labelReview')
            )
            ->disabled(
                fn (): bool => ! is_null($this->review->recommendation)
            )
            ->successNotificationTitle(__('translation.reviewSubmissionPage.successNotificationTitleReviewSubmittedSuccessfully'))
            ->action(function (Action $action) {
                $this->validateAllForms();

                // Can't submitted twice
                if ($this->review->recommendation === null) {
                    $this->review->update([
                        ...$this->reviewForm->getState()['reviewData'],
                        'recommendation' => $this->recommendation,
                    ]);

                    $editors = $this->record->participants()
                        ->whereHas('role', fn ($query) => $query->where('name', UserRole::Editor))
                        ->get()
                        ->pluck('user_id')
                        ->toArray();

                    $editors = User::whereIn('id', $editors)->get();
                    if ($editors->count()) {
                        Mail::to($editors)->send(
                            new ReviewCompleteMail($this->review)
                        );
                    }
                }

                $action->success();
            });
    }
}
