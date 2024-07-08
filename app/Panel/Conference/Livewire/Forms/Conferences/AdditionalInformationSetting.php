<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use Livewire\Component;
use Filament\Forms\Form;
use App\Models\Conference;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use App\Actions\Conferences\ConferenceUpdateAction;
use Filament\Forms\Components\Group;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class AdditionalInformationSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public Conference $conference;

    public ?array $formData = [];

    public function mount(Conference $conference): void
    {
        $this->form->fill([
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
                Section::make('')
                    ->schema([
                        Repeater::make('meta.additional_information')
                            ->label(__('translation.conferenceSettingsAbout.additionalinFormationLabel'))
                            ->helperText(__('translation.conferenceSetting.helperTextAdditional'))
                            ->schema([
                                TextInput::make('title')->required()
                                    ->label(__('translation.conferenceSetting.labelTitle')),
                                Toggle::make('is_shown')
                                    ->label(__('translation.conferenceSetting.labelAdditional'))
                                    ->default(true),
                                TinyEditor::make('content')->required()
                                    ->label(__('translation.conferenceSetting.labelContent')),
                            ])
                            ->collapsible(),
                    ]),

                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.conferenceSetting.successNotificationTitleAdditional'))
                        ->failureNotificationTitle(__('translation.conferenceSetting.failureNotificationTitleTitleAdditional'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                ConferenceUpdateAction::run($this->conference, $formData);
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
