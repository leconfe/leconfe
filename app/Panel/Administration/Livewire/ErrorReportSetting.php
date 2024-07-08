<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Settings\SettingUpdateAction;
use App\Facades\Setting;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class ErrorReportSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount()
    {
        $this->form->fill([
            'send_error_report' => Setting::get('send_error_report'),
        ]);
    }

    public function render()
    {
        return view('panel.administration.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('formData')
            ->schema([
                Section::make(__('translation.errorReportSetting.titleErrorReporting'))
                    ->description(new HtmlString(
                        __('translation.errorReportSetting.descriptionSendingReportOfTechnicalProblems')
                    ))
                    ->schema([
                        Toggle::make('send_error_report')
                            ->label(__('translation.errorReportSetting.labelTonggleSendErrorReportToLeconfe')),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.errorReportSetting.successNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                SettingUpdateAction::run($formData);

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),
            ]);
    }
}
