<?php

namespace App\Panel\Conference\Livewire\Forms\Submissions;

use App\Actions\Submissions\SubmissionUpdateAction;
use App\Panel\Conference\Resources\SubmissionResource;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class SubmissionWizardForm extends Component implements HasForms
{
    use InteractsWithForms;

    public $record;

    public function mount($record): void
    {
        $this->form->fill([
            'meta' => $record->getAllMeta(),
        ]);
    }

    protected function getFormModel(): string
    {
        return $this->record;
    }

    protected function getFormSchema(): array
    {
        return [
            Wizard::make([
                Wizard\Step::make('Details')
                    ->schema([
                        Section::make([
                            Section::make(__('translation.submissionWizard.sectionSubmissionDetails'))
                                ->description(__('translation.submissionWizard.descriptionSubmissionDetails'))
                                ->aside()
                                ->schema([
                                    TextInput::make('meta.title')
                                        ->label(__('translation.submissionWizard.labelTitle'))
                                        ->required(),
                                    SpatieTagsInput::make('keywords')
                                        ->placeholder('')
                                        ->model($this->record)
                                        ->label(__('translation.submissionWizard.labelKeywords'))
                                        ->type('submissionKeywords'),
                                    RichEditor::make('meta.abstract')
                                        ->label(__('translation.submissionWizard.labelAbstract'))
                                        ->required(),
                                ]),
                        ]),
                    ]),
                Wizard\Step::make('Upload Files')
                    ->schema([
                        Section::make([
                            Section::make(__('translation.submissionWizard.sectionUploadFiles'))
                                ->description(__('translation.submissionWizard.descriptionUploadFiles'))
                                ->aside()
                                ->schema([
                                    FileUpload::make('files')
                                        ->multiple()
                                        // ->required()
                                        ->previewable(false),
                                ]),
                        ]),
                    ]),
                Wizard\Step::make('Authors')
                    ->schema([
                        Section::make(__('translation.submissionWizard.labelAuthors'))
                            ->description()
                            ->aside()
                            ->schema([
                                ViewField::make('author')
                                    ->label('')
                                    ->view('test'),
                            ]),
                    ]),
                Wizard\Step::make('Review')
                    ->schema([
                        // ...
                    ]),
            ])
                ->submitAction(new HtmlString('<button type="submit">Submit</button>')),
        ];
    }

    public function render()
    {
        return view('panel.conference.livewire.forms.submissions.submission-wizard-form');
    }

    public function submit()
    {
        $data = $this->form->getState();
        SubmissionUpdateAction::run($data, $this->record);

        Notification::make()
            ->title(__('translation.submissionWizard.labelNewSubmission'))
            ->body(__('translation.submissionWizard.bodyNewSubmission').$this->record->getMeta('title'))
            ->warning()
            ->actions([
                Action::make('view')
                    ->label(__('translation.submissionWizard.labelViewSubmission'))
                    ->url(SubmissionResource::getUrl('view', $this->record)),
            ])
            ->sendToDatabase(auth()->user());
    }
}
