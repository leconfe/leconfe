<?php

namespace App\Panel\Conference\Livewire\Submissions\Forms;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Classes\Log;
use App\Models\Submission;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class Detail extends \Livewire\Component implements HasForms
{
    use InteractsWithForms;

    public Submission $submission;

    public array $meta = [];

    public array $topics = [];

    public function mount(Submission $submission)
    {
        $this->form->fill([
            'topics' => $this->submission->topics()->pluck('id')->toArray(),
            'meta' => $this->submission->getAllMeta()->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->disabled(function (): bool {
                return ! auth()->user()->can('editing', $this->submission);
            })
            ->model($this->submission)
            ->schema([
                TextInput::make('meta.title')
                ->label(__('translation.submissions.viewSubMissionDetailLabelTitle')),
                TextInput::make('meta.subtitle')
                ->label(__('translation.submissions.viewSubMissionDetailLabelSubtitle')),
                Select::make('topics')
                    ->preload()
                    ->multiple()
                    ->relationship('topics', 'name')
                    ->label(__('translation.submissions.viewSubMissionDetailLabelTopic'))
                    ->searchable(),
                SpatieTagsInput::make('meta.keywords')
                    ->splitKeys([','])
                    ->placeholder('')
                    ->label(__('translation.submissions.viewSubMissionDetailLabelKeywords'))
                    ->type('submissionKeywords'),
                TinyEditor::make('meta.abstract')
                    ->label(__('translation.submissions.viewSubMissionDetailLabelAbstract'))
                    ->required()
                    ->minHeight(300)
                    ->profile('basic'),
            ]);
    }

    public function submit(): void
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
            ->body('Saved successfully')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('panel.conference.livewire.submissions.forms.detail');
    }
}
