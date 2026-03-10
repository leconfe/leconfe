<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Models\Site;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class MastHeadSetting extends Component implements HasForms
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
                        Section::make(__('general.scheduled_conference_identity'))
                            ->description(__('general.information_about_the_scheduled_conference'))
                            ->aside()
                            ->schema([
                                TextInput::make('title')
                                    ->label(__('general.title'))
                                    ->autofocus()
                                    ->autocomplete()
                                    ->required()
                                    ->placeholder(__('general.enter_the_title_of_the_scheduled_conference')),
                                Grid::make()
                                    ->schema([
                                        DatePicker::make('date_start')
                                            ->label(__('general.start_date'))
                                            ->placeholder(__('general.enter_the_start_date_of_the_serie'))
                                            ->requiredWith('date_end'),
                                        DatePicker::make('date_end')
                                            ->label(__('general.end_date'))
                                            ->afterOrEqual('date_start')
                                            ->placeholder(__('general.enter_the_end_date_of_the_serie')),
                                    ]),
                                Textarea::make('meta.description')
                                    ->label(__('general.description'))
                                    ->rows(3)
                                    ->autosize()
                                    ->columnSpanFull()
                                    ->hint(__('general.recommended_description_length'))
                                    ->helperText(__('general.short_description_of_the_website')),
                                TextInput::make('meta.faculty')
                                    ->label(__('general.faculty')),
                                TextInput::make('meta.coordinator')
                                    ->label(__('general.coordinator'))
                                    ->helperText(__('general.coordinator_setting_description')),
                                Select::make('meta.category')
                                    ->label(__('general.categories'))
                                    ->searchable()
                                    ->multiple()
                                    ->options(fn() => collect(
                                        Site::getSite()->getMeta('scheduled_conference_categories', [])
                                    )->mapWithKeys(fn($item) => [$item => $item])->toArray()),
                                TextInput::make('meta.location')
                                    ->label(__('general.location'))
                                    ->helperText(__('general.location_description')),
                            ]),
                        Section::make(__('general.key_information'))
                            ->description(__('general.key_information_pricide_a_short_description'))
                            ->aside()
                            ->schema([
                                TinyEditor::make('meta.summary')
                                    ->label(__('general.conference_summary')),
                                TinyEditor::make('meta.editorial_team')
                                    ->label(__('general.editorial_team'))
                                    ->profile('basic')
                                    ->minHeight(100),
                            ]),
                        Section::make(__('general.description'))
                            ->aside()
                            ->description(__('general.include_about_your_conference'))
                            ->schema([
                                TinyEditor::make('meta.about')
                                    ->label(__('general.about_the_scheduled_conference'))
                                    ->profile('advanced'),
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
                                $scheduledConference = app()->getCurrentScheduledConference();

                                ScheduledConferenceUpdateAction::run($scheduledConference, $formData);

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                Log::error($th);
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),

            ])
            ->statePath('formData');
    }
}
