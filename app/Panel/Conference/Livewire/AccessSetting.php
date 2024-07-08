<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Actions\Settings\SettingUpdateAction;
use Livewire\Component;
use Filament\Forms\Form;
use App\Actions\Site\SiteUpdateAction;
use App\Facades\Setting;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;

class AccessSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount()
    {
        $this->form->fill(Setting::all());
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Checkbox::make('allow_registration')
                            ->label(__('translation.accessSetting.labelAllowRegistration'))
                            ->helperText(__('translation.accessSetting.helperAllowPublictoRegisterOnTheSite')),
                        Checkbox::make('must_verify_email')
                            ->label(__('translation.accessSetting.labelMustVerifyEmail'))
                            ->helperText(__('translation.accessSetting.helperRequireUsersToVerify'))
                    ])
                    ->columns(1),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.accessSetting.successNotificationTitleSaved'))
                        ->failureNotificationTitle(__('translation.accessSetting.failureNotificationTitleSaved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                Setting::update($formData);

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failure();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
