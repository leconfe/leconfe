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
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
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
            ->failureNotification(fn () => Notification::make()
                ->danger()
                ->title($this->getFailureNotificationTitle())
                ->body($this->getMissingRequiredUploadsNotificationBody()))
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
        $requiredTypeIds = $this->requiredUploadTypes()->pluck('id');

        if ($requiredTypeIds->isEmpty()) {
            return $this->record->submissionFiles()->exists();
        }

        return $this->missingRequiredUploadTypes()->isEmpty();
    }

    public function missingRequiredUploadTypeNames(): Collection
    {
        return $this->missingRequiredUploadTypes()->pluck('name')->values();
    }

    protected function getFailureNotificationTitle(): string
    {
        if ($this->missingRequiredUploadTypes()->isEmpty()) {
            return __('general.no_files_added_to_submission');
        }

        return __('general.required_submission_files_missing');
    }

    protected function getMissingRequiredUploadsNotificationBody(): ?string
    {
        $missingTypes = $this->missingRequiredUploadTypeNames();

        if ($missingTypes->isEmpty()) {
            return null;
        }

        return __('general.required_submission_file_types_missing', [
            'types' => $missingTypes->join(', '),
        ]);
    }

    protected function missingRequiredUploadTypes(): Collection
    {
        $requiredTypes = $this->requiredUploadTypes();

        if ($requiredTypes->isEmpty()) {
            return collect();
        }

        $requiredTypeIds = $requiredTypes->pluck('id');

        $uploadedRequiredTypeIds = $this->record->submissionFiles()
            ->whereIn('submission_file_type_id', $requiredTypeIds)
            ->distinct()
            ->pluck('submission_file_type_id');

        return $requiredTypes
            ->whereIn('id', $requiredTypeIds->diff($uploadedRequiredTypeIds))
            ->values();
    }

    protected function requiredUploadTypes(): Collection
    {
        return SubmissionFileType::withoutGlobalScopes()
            ->where('scheduled_conference_id', $this->record->scheduled_conference_id)
            ->where('required', true)
            ->orderBy('order_column')
            ->orderBy('id')
            ->get(['id', 'name']);
    }
}
