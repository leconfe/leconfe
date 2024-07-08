<?php

namespace App\Panel\Conference\Livewire\Submissions\Components\Files;

use App\Constants\SubmissionFileCategory;
use App\Panel\Conference\Livewire\Workflows\Classes\StageManager;

class PresenterFiles extends SubmissionFilesTable
{
    use Traits\CanSelectFiles;

    protected ?string $category = SubmissionFileCategory::PRESENTER_FILES;

    // protected string $tableHeading = 'Presenter Files';

    protected string $tableHeading;

    public function __construct()
    {
        $this->tableHeading = __('translation.submissions.presenterFilestableHeading');
    }

    public function isViewOnly(): bool
    {

        if ($this->viewOnly) {
            return $this->viewOnly;
        }

        return ! auth()->user()->can('uploadPresenterFiles', $this->submission);
    }

    public function getAcceptedFiles(): array
    {
        return StageManager::editing()
            ->getSetting(
                'production_allowed_file_types',
                ['pdf']
            );
    }

    public function getTargetCategory(): string
    {
        return $this->getCategory();
    }

    public function getSelectableCategories(): array
    {
        return [
            SubmissionFileCategory::PAPER_FILES,
            SubmissionFileCategory::REVISION_FILES,
            SubmissionFileCategory::EDITING_DRAFT_FILES,
        ];
    }
}
