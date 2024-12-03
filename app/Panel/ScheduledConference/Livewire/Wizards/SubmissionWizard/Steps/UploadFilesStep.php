<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Models\Submission;
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
            ->failureNotificationTitle(__('general.no_files_added_to_submission'))
            ->successNotificationTitle(__('general.saved'))
            ->action(function (PageAction $action) {
                if (! $this->record->submissionFiles()->exists()) {
                    return $action->failure();
                }
                $action->success();
                $this->dispatch('next-wizard-step');
            });
    }
}
