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
use Filament\Forms\Components\Checkbox;
use Livewire\Component;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Support\Facades\Config;

class SelectLanguage extends Component implements HasForms
{
    use InteractsWithForms;

    public Conference $conference;
    public ?array $formData = [];

    public function mount(Conference $conference): void
    {
        $this->conference = $conference;
        $this->form->fill([
            ...$conference->attributesToArray(),
            'meta' => $conference->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.select-language');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        CheckboxList::make('meta.languages')
                            ->options(
                                Config::get('app.locales')
                            ),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.setupSetting.setupSettingSuccessNotificationTitle'))
                        ->failureNotificationTitle(__('translation.setupSetting.setupSettingFailureNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                ConferenceUpdateAction::run($this->conference, $formData);
                                $action->sendSuccessNotification();
                                $this->js('window.location.reload()');
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }

}
