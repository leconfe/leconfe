<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Models\Submission;
use App\Models\SubmissionFileType;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Filament\Actions\Action as PageAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

class UploadFilesStep extends Component implements HasActions, HasForms, HasWizardStep
{
    use InteractsWithActions, InteractsWithForms;

    public Submission $record;

    protected $listeners = ['refreshLivewire' => '$refresh'];

    public static function getWizardLabel(): string
    {
        return __('general.upload_files');
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.wizards.submission-wizard.steps.upload-files-step');
    }

    public function nextStep()
    {
        return PageAction::make('nextStep')
            ->label(__('general.next'))
            ->failureNotificationTitle(__('general.required_submission_files_missing'))
            ->successNotificationTitle(__('general.saved'))
            ->action(function (PageAction $action) {
                if (! $this->hasRequiredUploads()) {
                    return $action->failure();
                }
                $action->success();
                $this->dispatch('next-wizard-step');
            });
    }

    public function hasRequiredUploads(): bool
    {
        $requiredTypeIds = SubmissionFileType::withoutGlobalScopes()
            ->where('scheduled_conference_id', $this->record->scheduled_conference_id)
            ->where('required', true)
            ->pluck('id');

        if ($requiredTypeIds->isEmpty()) {
            return $this->record->submissionFiles()->exists();
        }

        $uploadedRequiredTypeIds = $this->record->submissionFiles()
            ->whereIn('submission_file_type_id', $requiredTypeIds)
            ->distinct()
            ->pluck('submission_file_type_id');

        return $requiredTypeIds->diff($uploadedRequiredTypeIds)->isEmpty();
    }
}
