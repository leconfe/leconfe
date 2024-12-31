<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Models\Submission;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Livewire\Component;

class ContributorsStep extends Component implements HasWizardStep
{
    public Submission $record;

    public static function getWizardLabel(): string
    {
        return __('general.contributors');
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.wizards.submission-wizard.steps.authors-step');
    }

    public function nextStep()
    {
        $this->dispatch('refreshLivewire');
        $this->dispatch('refreshAbstractsFiles');
        $this->dispatch('next-wizard-step');
    }
}
