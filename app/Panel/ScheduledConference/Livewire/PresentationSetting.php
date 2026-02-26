<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Models\Timeline;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class PresentationSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'open_date' => Timeline::type(Timeline::TYPE_PRESENTATION_OPEN)->value('date'),
            'close_date' => Timeline::type(Timeline::TYPE_PRESENTATION_CLOSE)->value('date'),
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
                Section::make('Presentation Settings')
                    ->columns(1)
                    ->schema([
                        DatePicker::make('open_date')
                            ->label('Presentation Open Date'),
                        DatePicker::make('close_date')
                            ->afterOrEqual('open_date')
                            ->label('Presentation Close Date'),
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

                                if (data_get($formData, 'open_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_PRESENTATION_OPEN,
                                    ], [
                                        'name' => 'Presentation Open',
                                        'date' => Date::parse(data_get($formData, 'open_date')),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_PRESENTATION_OPEN)->delete();
                                }

                                if (data_get($formData, 'close_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_PRESENTATION_CLOSE,
                                    ], [
                                        'name' => 'Presentation Close',
                                        'date' => Date::parse(data_get($formData, 'close_date')),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_PRESENTATION_CLOSE)->delete();
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
