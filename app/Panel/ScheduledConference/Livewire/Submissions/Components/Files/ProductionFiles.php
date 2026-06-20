<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Constants\SubmissionFileCategory;

class ProductionFiles extends SubmissionFilesTable
{
    use Traits\CanSelectFiles;

    protected ?string $category = SubmissionFileCategory::EDITED_FILES;

    protected string $tableHeading;

    public function __construct()
    {
        $this->tableHeading = __('general.production_files');
    }

    public function isViewOnly(): bool
    {
        if ($this->viewOnly) {
            return $this->viewOnly;
        }

        return ! auth()->user()->can('editing', $this->submission);
    }

    public function getTargetCategory(): string
    {
        return $this->getCategory();
    }

    public function getSelectableCategories(): array
    {
        return [
            SubmissionFileCategory::REVIEW_FILES,
            SubmissionFileCategory::PRESENTATION_FILES,
            SubmissionFileCategory::REVIEWER_FILES,
            SubmissionFileCategory::REVISION_FILES,
            SubmissionFileCategory::EDITING_DRAFT_FILES,
        ];
    }
}
