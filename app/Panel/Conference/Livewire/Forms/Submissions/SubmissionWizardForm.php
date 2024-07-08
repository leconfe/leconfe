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
                            Section::make('Submission Details')
                                ->description('Please provide the following details to help us manage your submission in our system.')
                                ->aside()
                                ->schema([
                                    TextInput::make('meta.title')
                                        
                                        ->required(),
                                    SpatieTagsInput::make('keywords')
                                        ->placeholder('')
                                        ->model($this->record)
                                        ->type('submissionKeywords'),
                                    RichEditor::make('meta.abstract')
                                        ->required(),
                                ]),
                        ]),
                    ]),
                Wizard\Step::make('Upload Files')
                    ->schema([
                        Section::make([
                            Section::make('Upload Files')
                                ->description('Provide any files our editorial team may need to evaluate your submission. In addition to the main work, you may wish to submit data sets, conflict of interest statements, or other supplementary files if these will be helpful for our editors.')
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
                        Section::make('Authors')
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
            ->title('New Submission')
            ->body('A new paper has been submitted to which an editor needs to be assigned. '.$this->record->getMeta('title'))
            ->warning()
            ->actions([
                Action::make('view')
                    ->label('View Submission')
                    ->url(SubmissionResource::getUrl('view', $this->record)),
            ])
            ->sendToDatabase(auth()->user());
    }
}
