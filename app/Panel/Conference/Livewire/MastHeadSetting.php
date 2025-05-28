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
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MastHeadSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $conference = app()->getCurrentConference();
        $this->form->fill([
            'name' => $conference->getTranslations('name'),
            'description' => $conference->getTranslations('description'),
            'summary' => $conference->getTranslations('summary'),
            'about' => $conference->getTranslations('about'),
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
                                    TextInput::make('name')
                                        ->label(__('general.name'))
                                        ->autofocus()
                                        ->autocomplete()
                                        ->required(),
                                ]),

                                TextInput::make('meta.issn')
                                    ->label(__('general.ISSN'))
                                    ->helperText(__('general.the_issn_of_the_conference')),
                                MultilanguageComponent::make([
                                    Textarea::make('description')
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

                        MultilanguageComponent::make([
                            Section::make(__('general.key_information'))
                                ->description(__('general.provide_short_description_your_conference'))
                                ->aside()
                                ->schema([
                                    TinyEditor::make('summary')
                                        ->label(__('general.conference_summary')),
                                ]),
                            Section::make(__('general.description'))
                                ->aside()
                                ->description(__('general.include_about_your_conference'))
                                ->schema([
                                    TinyEditor::make('about')
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
                            $data = $this->form->getState();
                            $conference = $this->form->getRecord();
                            try {
                                DB::beginTransaction();

                                $conference->setTranslations('name', $data['name']);
                                $conference->setTranslations('about', $data['about']);
                                $conference->setTranslations('description', $data['description']);
                                $conference->setTranslations('summary', $data['summary']);

                                if(array_key_exists('meta', $data)){
                                    $conference->setManyMeta($data['meta']);
                                }
                                
                                DB::commit();

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                DB::rollBack();
                                throw $th;
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),

            ])
            ->statePath('formData');
    }
}
