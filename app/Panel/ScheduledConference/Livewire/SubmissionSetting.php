<?php

namespace App\Panel\ScheduledConference\Livewire;

use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class SubmissionSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $this->form->fill([
            'submission_open_date' => $scheduledConference->getMeta('submission_open_date'),
            'submission_close_date' => $scheduledConference->getMeta('submission_close_date'),
        ]);
    }

    public function render()
    {
        return view('forms.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentScheduledConference())
            ->schema([
                Section::make(__('general.submission_setting'))
                    ->columns(1)
                    ->schema([
                        DatePicker::make('submission_open_date')
                            ->label(__('general.submission_setting.open_date')),
                        DatePicker::make('submission_close_date')
                            ->afterOrEqual('open_date')
                            ->label(__('general.submission_setting.close_date')),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            $scheduledConference = app()->getCurrentScheduledConference();

                            try {
                                DB::beginTransaction();

                                $scheduledConference->setManyMeta($formData);

                                DB::commit();
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failureNotificationTitle($th->getMessage());
                                $action->sendFailureNotification();

                                DB::rollBack();
                                throw $th;
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
