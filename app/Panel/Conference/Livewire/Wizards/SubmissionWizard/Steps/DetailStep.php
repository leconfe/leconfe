<?php

namespace App\Panel\Conference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Models\Submission;
use App\Panel\Conference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class DetailStep extends Component implements HasActions, HasForms, HasWizardStep
{
    use InteractsWithActions, InteractsWithForms;

    public Submission $record;

    public array $meta;

    public array $topic;

    public string $nextStep = 'upload-files';

    protected $listeners = ['refreshLivewire' => '$refresh'];

    public function mount(Submission $record)
    {
        $this->form->fill([
            'topic' => $record->topics()->pluck('id')->toArray(),
            'meta' => $record->getAllMeta()->toArray(),
        ]);
    }

    public static function getWizardLabel(): string
    {
        return __('translation.submissions.getWizardLabelDetails');
    }

    protected function getFormModel()
    {
        return $this->record;
    }

    public function render()
    {
        return view('panel.conference.livewire.wizards.submission-wizard.steps.detail-step');
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make([
                Section::make(__('translation.submissions.wizardDetailStepTitleSubmissionDetails'))
                    ->description(__('translation.submissions.wizardDetailStepDescriptionPleaseHelpUs'))
                    ->aside()
                    ->schema([
                        Hidden::make('nextStep'),
                        Select::make('topic')
                            ->preload()
                            ->multiple()
                            ->label(__('translation.submissions.wizardDetailStepLabelTopic'))
                            ->searchable()
                            ->relationship('topics', 'name'),
                        TextInput::make('meta.title')
                            ->label(__('translation.submissions.wizardDetailStepLabelTitle'))
                            ->required(),
                        SpatieTagsInput::make('meta.keywords')
                            ->label(__('translation.submissions.wizardDetailStepLabelKeywords'))
                            ->splitKeys([','])
                            ->placeholder('')
                            ->model($this->record)
                            ->type('submissionKeywords'),
                        TinyEditor::make('meta.abstract')
                            ->label(__('translation.submissions.wizardDetailStepLabelAbstract'))
                            ->minHeight(300)
                            ->required()
                            ->profile('basic'),
                    ]),
            ]),
        ];
    }

    public function nextStep()
    {
        return Action::make('nextStep')
            ->label(__('translation.button.next'))
            ->successNotificationTitle('Saved')
            ->action(function (Action $action) {
                $this->record = SubmissionUpdateAction::run($this->form->getState(), $this->record);
                $this->dispatch('next-wizard-step');
                $action->success();
            });
    }
}
