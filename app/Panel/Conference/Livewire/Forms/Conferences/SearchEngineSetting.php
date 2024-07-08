<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class SearchEngineSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            ...app()->getCurrentConference()->attributesToArray(),
            'meta' => app()->getCurrentConference()->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentConference())
            ->schema([
                Section::make()
                    ->schema([
                        KeyValue::make('meta.meta_tags')
                            ->label(__('translation.searchEngineSetting.labelMetatags'))
                            ->keyLabel(__('translation.searchEngineSetting.keyLabel'))
                            ->valueLabel(__('translation.searchEngineSetting.valueLabel'))
                            ->helperText(__('translation.searchEngineSetting.helpertext')),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.searchEngineSetting.successNotificationTitle'))
                        ->failureNotificationTitle(__('translation.searchEngineSetting.failureNotificationTitle'))
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
