<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Models\Submission;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Livewire\Component;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;

class ContributorsStep extends Component implements HasWizardStep, HasActions, HasForms
{
    use InteractsWithActions, InteractsWithForms;

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
        return Action::make('nextStep')
            ->label(__('general.next'))
            ->icon('heroicon-o-chevron-right')
            ->iconPosition('after')
            ->failureNotificationTitle(__('general.must_add_one_author'))
            ->successNotificationTitle(__('general.saved'))
            ->action(function (Action $action) {
                if (! $this->record->authors()->exists()) {
                    return $action->failure();
                }
                $action->success();
                $this->dispatch('refreshLivewire');
                $this->dispatch('next-wizard-step');
            });
    }
}
