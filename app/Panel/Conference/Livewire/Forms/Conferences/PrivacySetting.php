<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class PrivacySetting extends Component implements HasForms
{
    use InteractsWithForms;

    public Conference $conference;

    public ?array $formData = [];

    public function mount(Conference $conference): void
    {
        $this->form->fill([
            ...$conference->attributesToArray(),
            'meta' => $conference->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->conference)
            ->schema([
                Section::make()
                    ->schema([
                        TinyEditor::make('meta.privacy_statement')
                            ->label(__('translation.privacySetting.labelPrivacyStatement')),
                    ])
                    ->extraAttributes([
                        'class' => '!p-0',
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.privacySetting.successNotificationTitleSaved'))
                        ->failureNotificationTitle(__('translation.privacySetting.failureNotificationTitleDataCould'))
                        ->action(function (Action $action) {
                            $privacyStatementFormData = $this->form->getState();
                            try {
                                ConferenceUpdateAction::run($this->conference, $privacyStatementFormData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
