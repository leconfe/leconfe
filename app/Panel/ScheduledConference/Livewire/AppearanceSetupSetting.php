<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Stevebauman\Purify\Facades\Purify;

class AppearanceSetupSetting extends Component implements HasForms
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
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->label(__('general.logo'))
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb'),
                        SpatieMediaLibraryFileUpload::make('favicon')
                            ->collection('favicon')
                            ->label('Favicon')
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb'),
                        SpatieMediaLibraryFileUpload::make('thumbnail')
                            ->label(__('general.scheduled_conference_thumbnail'))
                            ->collection('thumbnail')
                            ->helperText(__('general.image_representation_of_the_serie_will_uses'))
                            ->image()
                            ->conversion('thumb'),
                        MultilanguageComponent::make([
                            TinyEditor::make('meta.page_footer')
                            ->label(__('general.page_footer'))
                            ->profile('advanced')
                            ->minHeight(300)
                            ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                        ]),
                        
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
