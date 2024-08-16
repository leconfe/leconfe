<?php

namespace App\Panel\ScheduledConference\Livewire\Submissions\Forms;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Submission;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Textarea;

class References extends \Livewire\Component implements HasForms
{
    use InteractsWithForms;

    public Submission $submission;

    public array $meta = [];

    public function mount(Submission $submission)
    {
        $this->form->fill([
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);
    }

    public function submit()
    {
        SubmissionUpdateAction::run(
            $this->form->getState(),
            $this->submission
        );

        Log::make(
            name: 'submission',
            subject: $this->submission,
            description: __('general.submission_metadata_updated')
        )
            ->by(auth()->user())
            ->save();

        Notification::make()
            ->body(__('general.saved_successfuly'))
            ->success()
            ->send();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Textarea::make('meta.references')
                    ->label(__('general.references'))
                    ->hiddenLabel()
                    ->autosize(),
            ]);
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.submissions.forms.references');
    }
}
