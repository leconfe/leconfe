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

    public function __construct()
    {
        $this->tableHeading = __('general.revisions');
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

        $activeRoundId = $this->submission->reviewRounds()
            ->open()
            ->orderByDesc('round_number')
            ->value('id');

        return $activeRoundId && (int) $activeRoundId === $this->reviewRoundId;
    }

    public function isViewOnly(): bool
    {
        if ($this->submission->stage !== SubmissionStage::PeerReview) {
            return true;
        }

        if (! $this->isSelectedRoundOpen()) {
            return true;
        }

        return ! $this->canUploadRevisionFiles();
    }

    public function uploadFormSchema(): array
    {
        return [
            Shout::make('information')
                ->content(__('general.after_uploading_files_system_will_send_notification_to_editor')),
            Shout::make('revision_deadline')
                ->type('warning')
                ->content(fn () => __('general.revision_due_at_notice', [
                    'date' => $this->submission->revision_due_at?->format('Y-m-d H:i'),
                ]))
                ->visible(fn (): bool => $this->submission->revision_due_at !== null),
            ...parent::uploadFormSchema(),
        ];
    }

    public function handleUploadAction(array $data, $action)
    {
        if (! $this->canUploadRevisionFiles()) {
            $action->failureNotificationTitle(__('general.revision_deadline_passed'));
            $action->failure();

            return;
        }

        parent::handleUploadAction($data, $action);
    }

    protected function canUploadRevisionFiles(): bool
    {
        if ($this->viewOnly || $this->submission->stage !== SubmissionStage::PeerReview || ! $this->isSelectedRoundOpen()) {
            return false;
        }

        $user = auth()->user();

        if (! $user?->can('uploadRevisionFiles', $this->submission)) {
            return false;
        }

        if ($user->can('actAsEditor', $this->submission)) {
            return true;
        }

        return $this->submission->revision_required;
    }
}
