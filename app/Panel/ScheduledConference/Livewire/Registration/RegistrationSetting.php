<?php

namespace App\Panel\ScheduledConference\Livewire\Registration;

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

class RegistrationSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'open_date' => Timeline::type(Timeline::TYPE_REGISTRATION_OPEN)->value('date'),
            'close_date' => Timeline::type(Timeline::TYPE_REGISTRATION_CLOSE)->value('date'),
            'hide_from_timeline' => Timeline::type(Timeline::TYPE_REGISTRATION_OPEN)->value('hide') || Timeline::type(Timeline::TYPE_REGISTRATION_CLOSE)->value('hide'),
            'meta' => [
                'registration_policy' => app()->getCurrentScheduledConference()->getMeta('registration_policy'),
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->disabled(fn () => auth()->user()->cannot('RegistrationSetting:update'))
            ->schema([
                Section::make()
                    ->schema([
                        DatePicker::make('open_date')
                            ->label(__('general.registration_setting.open_date')),
                        DatePicker::make('close_date')
                            ->afterOrEqual('open_date')
                            ->label(__('general.registration_setting.close_date')),
                        Toggle::make('hide_from_timeline')
                            ->label(__('general.submission_setting.hide_from_timeline'))
                            ->label('Hide from timeline'),
                        TinyEditor::make('meta.registration_policy')
                            ->label(__('general.registration_policy'))
                            ->plugins('advlist autoresize codesample directionality emoticons fullscreen hr image imagetools link lists media table toc wordcount code')
                            ->toolbar('undo redo removeformat | formatselect fontsizeselect | bold italic | rtl ltr | alignjustify alignright aligncenter alignleft | numlist bullist | forecolor backcolor | blockquote table hr | image link code')
                            ->minHeight(300),
                    ]),
                Actions::make([
                    Action::make('Save changes')
                        ->label(__('general.save_changes'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                DB::beginTransaction();

                                if (data_get($formData, 'open_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_REGISTRATION_OPEN,
                                    ], [
                                        'name' => 'Registration Open',
                                        'date' => Date::parse(data_get($formData, 'open_date')),
                                        'hide' => data_get($formData, 'hide_from_timeline'),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_REGISTRATION_OPEN)->delete();
                                }

                                if (data_get($formData, 'close_date')) {
                                    Timeline::updateOrCreate([
                                        'type' => Timeline::TYPE_REGISTRATION_CLOSE,
                                    ], [
                                        'name' => 'Registration Close',
                                        'date' => Date::parse(data_get($formData, 'close_date')),
                                        'hide' => data_get($formData, 'hide_from_timeline'),
                                    ]);
                                } else {
                                    Timeline::type(Timeline::TYPE_REGISTRATION_CLOSE)->delete();
                                }

                                ScheduledConferenceUpdateAction::run(app()->getCurrentScheduledConference(), $formData);

                                DB::commit();

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->failureNotificationTitle($th->getMessage());
                                $action->sendFailureNotification();
                                DB::rollBack();
                                throw $th;
                            }
                        }),
                ])->alignRight(),
            ])->statePath('formData');
    }

    public function render()
    {
        return view('forms.form');
    }
}
