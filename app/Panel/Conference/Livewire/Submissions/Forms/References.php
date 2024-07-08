<?php

namespace App\Panel\Conference\Livewire\Submissions\Forms;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Submission;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

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
            description: __('log.submission.metadata_updated')
        )
            ->by(auth()->user())
            ->save();

        Notification::make()
            ->body(__('translation.submissions.referenceBodySavedSuccessfully'))
            ->success()
            ->send();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TinyEditor::make('meta.references')
                    ->label(__('translation.submissions.referenceLabelReferences'))
                    ->hiddenLabel()
                    ->minHeight(300),
            ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.submissions.forms.references');
    }
}
