<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class PaymentSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'meta' => app()->getCurrentScheduledConference()->getAllMeta(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Checkbox::make('meta.submission_payment')
                            ->label(__('general.enable_submission_payment')),
                        Checkbox::make('meta.participant_payment')
                            ->label(__('general.enable_participant_payment')),
                        TinyEditor::make('meta.payment_policy')
                            ->label(__('general.payment_policy')),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();

                            try {
                                ScheduledConferenceUpdateAction::run(app()->getCurrentScheduledConference(), $formData);
                            } catch (\Throwable $th) {
                                $action->failure();
                                throw $th;
                            }

                            $action->success();
                        }),
                ]),
            ])
            ->disabled(fn () => ! auth()->user()->can('update', app()->getCurrentScheduledConference()))
            ->statePath('formData');
    }

    public function render()
    {
        return view('forms.form');
    }
}
