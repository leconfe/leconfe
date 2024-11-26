<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Models\Enums\UserRole;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Jackiedo\Timezonelist\Facades\Timezonelist;
use Livewire\Component;
use Squire\Models\Country;

class SetupSetting extends Component implements HasForms
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
                        Checkbox::make('meta.allow_registration')
                            ->label(__('general.allow_registration'))
                            ->helperText(__('general.allow_registration_helper')),
                        CheckboxList::make('meta.allowed_self_assign_roles')
                            ->label('Allow User to self register as role')
                            ->options(UserRole::selfAssignedRoleNames()),
                        Select::make('meta.default_register_country')
                            ->label(__('general.default_register_country'))
                            ->placeholder(__('general.select_a_country'))
                            ->searchable()
                            ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->id => $country->flag.' '.$country->name]))
                            ->optionsLimit(250),
                        Select::make('meta.timezone')
                            ->options(Timezonelist::toArray(false))
                            ->selectablePlaceholder(false)
                            ->searchable()
                            ->required(),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
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
