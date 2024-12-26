<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Models\Timeline;
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
        $this->form->fill([
            'open_date' => Timeline::type(Timeline::TYPE_SUBMISSION_OPEN)->value('date'),
            'close_date' => Timeline::type(Timeline::TYPE_SUBMISSION_CLOSE)->value('date'),
            'hide_from_timeline' => Timeline::type(Timeline::TYPE_SUBMISSION_OPEN)->value('hide') || Timeline::type(Timeline::TYPE_SUBMISSION_CLOSE)->value('hide'),
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
                        DatePicker::make('open_date')
                            ->label(__('general.submission_setting.open_date')),
                        DatePicker::make('close_date')
                            ->afterOrEqual('open_date')
                            ->label(__('general.submission_setting.close_date')),
                        Toggle::make('hide_from_timeline')
                            ->label(__('general.submission_setting.hide_from_timeline'))
                            ->label('Hide from timeline'),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                DB::beginTransaction();
                                
                                if(data_get($formData, 'open_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_SUBMISSION_OPEN,
                                    ], [
                                        'name' => 'Submission Open',
                                        'date' => Date::parse(data_get($formData, 'open_date')),
                                        'hide' => data_get($formData, 'hide_from_timeline'),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_SUBMISSION_OPEN)->delete();
                                }

                                if(data_get($formData, 'close_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_SUBMISSION_CLOSE,
                                    ], [
                                        'name' => 'Submission Close',
                                        'date' => Date::parse(data_get($formData, 'close_date')),
                                        'hide' => data_get($formData, 'hide_from_timeline'),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_SUBMISSION_CLOSE)->delete();
                                }


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
