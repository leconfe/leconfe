<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
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
        $conference = app()->getCurrentConference();

        $this->form->fill([
            ...$conference->attributesToArray(),
            'meta' => $conference->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('forms.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentConference())
            ->schema([
                Section::make()
                    ->schema([
                        Section::make(__('general.conference_identity'))
                            ->description(__('general.information_about_scheduled_conference'))
                            ->aside()
                            ->schema([
                                MultilanguageComponent::make([
                                    TextInput::make('meta.name')
                                    ->label(__('general.name'))
                                    ->autofocus()
                                    ->autocomplete()
                                    ->required(),
                                ]),
                                
                                TextInput::make('meta.issn')
                                    ->label(__('general.ISSN'))
                                    ->helperText(__('general.the_issn_of_the_conference')),
                                MultilanguageComponent::make([
                                    Textarea::make('meta.description')
                                    ->label(__('general.description'))
                                    ->rows(3)
                                    ->autosize()
                                    ->columnSpanFull()
                                    ->hint(__('general.recommended_description_length'))
                                    ->helperText(__('general.short_description_of_the_website')),
                                ]),
                                
                                Select::make('meta.scope')
                                    ->options([
                                        Conference::SCOPE_INTERNATIONAL => __('general.international'),
                                        Conference::SCOPE_NATIONAL => __('general.national'),
                                    ])
                                    ->helperText(__('general.conference_scope_description')),
                            ]),
                        Section::make(__('general.key_information'))
                            ->description(__('general.provide_short_description_your_conference'))
                            ->aside()
                            ->schema([
                                MultilanguageComponent::make([
                                    TinyEditor::make('meta.summary')
                                    ->label(__('general.conference_summary')),
                                ]),
                            ]),
                        Section::make(__('general.description'))
                            ->aside()
                            ->description(__('general.include_about_your_conference'))
                            ->schema([
                                MultilanguageComponent::make([
                                    TinyEditor::make('meta.about')
                                    ->label(__('general.about_the_conference'))
                                    ->profile('basic'),
                                ]),
                                
                            ]),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            try {
                                ConferenceUpdateAction::run($this->form->getRecord(), $this->form->getState());
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
