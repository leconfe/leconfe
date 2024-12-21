<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Components\Files;

use App\Constants\SubmissionFileCategory;

class PaperFiles extends SubmissionFilesTable
{
    protected ?string $category = SubmissionFileCategory::PAPER_FILES;

    protected string $tableHeading;

    public function __construct()
    {
        $this->tableHeading = __('general.papers');
    }

    public function isViewOnly(): bool
    {
        return $this->viewOnly || ! auth()->user()->can('uploadPaper', $this->submission);
    }
}
