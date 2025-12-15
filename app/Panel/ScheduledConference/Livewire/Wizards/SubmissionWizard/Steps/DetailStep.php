<?php

namespace App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Steps;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Models\Submission;
use App\Models\Topic;
use App\Panel\ScheduledConference\Livewire\Wizards\SubmissionWizard\Contracts\HasWizardStep;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;

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
        return __('general.details');
    }

    protected function getFormModel()
    {
        return $this->record;
    }

    public function render()
    {
        return view('panel.scheduledConference.livewire.wizards.submission-wizard.steps.detail-step');
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make([
                Section::make(__('general.submission_details'))
                    ->description(__('general.provide_details_to_help_us'))
                    ->aside()
                    ->schema([
                        Hidden::make('nextStep'),
                        Select::make('topic')
                            ->visible(fn() => Topic::query()->count())
                            ->preload()
                            ->multiple()
                            ->label(__('general.topic'))
                            ->searchable()
                            ->relationship('topics', 'name'),
                        TextInput::make('meta.title')
                            ->label(__('general.title'))
                            ->required(),
                        TagsInput::make('meta.keywords')
                            ->label(__('general.keywords'))
                            ->splitKeys([','])
                            ->placeholder(''),
                        TinyEditor::make('meta.abstract')
                            ->label(__('general.abstract'))
                            ->minHeight(300)
                            ->minLength($this->record?->track->getMeta('abstract_word_count') ?? 0)
                            ->required(! $this->record?->track->getMeta('do_not_require_abstracts') ?? true),
                    ]),
            ]),
        ];
    }

    public function nextStep()
    {
        return Action::make('nextStep')
            ->label(__('general.next'))
            ->successNotificationTitle(__('general.saved'))
            ->action(function (Action $action) {
                $this->record = SubmissionUpdateAction::run($this->form->getState(), $this->record);
                $this->dispatch('next-wizard-step');
                $action->success();
            });
    }
}
