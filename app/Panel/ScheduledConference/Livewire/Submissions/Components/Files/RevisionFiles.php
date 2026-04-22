<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Constants\SubmissionFileCategory;
use App\Models\Enums\SubmissionStage;
use App\Models\Submission;
use Awcodes\Shout\Components\Shout;
use Livewire\Attributes\On;

class RevisionFiles extends SubmissionFilesTable
{
    protected ?string $category = SubmissionFileCategory::REVISION_FILES;

    protected string $tableHeading;

    protected string $tableDescription;

    public function __construct()
    {
        $this->tableHeading = __('general.revisions');
        $this->tableDescription = __('general.upload_your_reviews_files_here');
    }

    public function mount(Submission $submission): void
    {
        $this->submission = $submission;
        $this->reviewRoundId = $submission->activeReviewRound?->getKey()
            ?? $submission->latestReviewRound?->getKey();
    }

    #[On('peer-review-round-selected')]
    public function onReviewRoundSelected(int $roundId): void
    {
        $this->reviewRoundId = $roundId;
        $this->resetTable();
    }

    protected function shouldFilterByReviewRound(): bool
    {
        return true;
    }

    protected function resolveUploadReviewRoundId(): ?int
    {
        return $this->reviewRoundId;
    }

    protected function isSelectedRoundOpen(): bool
    {
        if (! $this->reviewRoundId) {
            return false;
        }

        return (bool) $this->submission->reviewRounds()
            ->whereKey($this->reviewRoundId)
            ->open()
            ->exists();
    }

    public function isViewOnly(): bool
    {
        if ($this->submission->stage !== SubmissionStage::PeerReview) {
            return true;
        }

        if (! $this->isSelectedRoundOpen()) {
            return true;
        }

        return ! auth()->user()->can('uploadRevisionFiles', $this->submission) && ! $this->submission->revision_required;
    }

    public function uploadFormSchema(): array
    {
        return [
            Shout::make('information')
                ->content(__('general.after_uploading_files_system_will_send_notification_to_editor')),
            ...parent::uploadFormSchema(),
        ];
    }
}
