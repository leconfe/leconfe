<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Models\Review;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;

class ReviewSetupSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $this->form->fill([
            ...$scheduledConference->attributesToArray(),
            'meta' => $scheduledConference->getAllMeta(),
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
                Section::make()
                    ->schema([
                        Radio::make('meta.review_mode')
                            ->label(__('general.review_mode'))
                            ->options(Review::getModeOptions()),
                        TextInput::make('meta.review_invitation_response_deadline')
                            ->required()
                            ->label(__('general.default_response_deadline'))
                            ->helperText(__('general.deadline_reviewers_invitations'))
                            ->numeric()
                            ->minValue(1)
                            ->suffix(__('general.days')),
                        TextInput::make('meta.review_completion_deadline')
                            ->required()
                            ->label(__('general.default_completion_deadline'))
                            ->helperText(__('general.default_completing_deadline'))
                            ->numeric()
                            ->minValue(1)
                            ->afterOrEqual('meta.review_invitation_response_deadline')
                            ->suffix(__('general.days')),
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
