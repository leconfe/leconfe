<?php

namespace App\Panel\ScheduledConference\Livewire;

use App\Actions\ScheduledConferences\ScheduledConferenceUpdateAction;
use App\Forms\Components\TinyEditor;
use App\Models\Topic;
use App\Actions\Topics\TopicCreateAction;
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
                                    ->label(__('general.faculty'))
                                    ->rule('alpha_dash'),
                                TextInput::make('meta.coordinator')
                                    ->label(__('general.coordinator'))
                                    ->helperText(__('general.coordinator_setting_description')),
                                Select::make('meta.topics')
                                    ->multiple()
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search): array => Topic::where('name', 'like', "%{$search}%")->limit(50)->pluck('name', 'id')->toArray())
                                    ->getOptionLabelsUsing(fn(array $values): array => Topic::whereIn('id', $values)->pluck('name', 'id')->toArray())
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->label(__('general.topic_name'))
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data): int {
                                        $topic = TopicCreateAction::run(['name' => $data['name']]);

                                        return $topic->getKey();
                                    })
                                    ->noSearchResultsMessage(__('general.no_topic_found_create_new'))
                                    ->label(__('general.topic')),
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
