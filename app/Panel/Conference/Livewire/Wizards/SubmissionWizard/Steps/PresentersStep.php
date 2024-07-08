<?php

namespace App\Panel\Conference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Models\Submission;
use App\Panel\Conference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Livewire\Component;

class PresentersStep extends Component implements HasWizardStep
{
    public Submission $record;

    public static function getWizardLabel(): string
    {
        return __('translation.submissions.getWizardLabelPresenters');
    }

    public function render()
    {
        return view('panel.conference.livewire.wizards.submission-wizard.steps.presenters-step');
    }

    public function nextStep()
    {
        $this->dispatch('refreshLivewire');
        $this->dispatch('refreshAbstractsFiles');
        $this->dispatch('next-wizard-step');
    }
}
