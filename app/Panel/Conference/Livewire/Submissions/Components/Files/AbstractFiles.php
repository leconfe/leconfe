<?php

namespace App\Panel\Conference\Livewire\Submissions\Components\Files;

use App\Constants\SubmissionFileCategory;
use App\Panel\Conference\Livewire\Workflows\Classes\StageManager;

class AbstractFiles extends SubmissionFilesTable
{
    protected ?string $category = SubmissionFileCategory::ABSTRACT_FILES;

    // protected string $tableHeading = 'Abstract Files';

    protected string $tableHeading;

    public function __construct()
    {
        $this->tableHeading = __('translation.submissions.uploadFilesStepTableHeading');
    }

    protected $listeners = [
        'refreshAbstractsFiles' => '$refresh',
    ];

    public function getTargetCategory(): string
    {
        return $this->getCategory();
    }

    public function getAcceptedFiles(): array
    {
        return StageManager::callForAbstract()
            ->getSetting(
                'allowed_file_types',
                parent::ACCEPTED_FILE_TYPES
            );
    }

    public function isViewOnly(): bool
    {
        if ($this->viewOnly) {
            return true;
        }

        return ! auth()->user()->can('uploadAbstract', $this->submission);
    }
}
