<?php

namespace App\Panel\Administration\Livewire;

use App\Facades\Setting;
use App\Support\Multilingual\ContentLocales;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Arr;
use Livewire\Component;

class LanguageSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $locales = app(ContentLocales::class);

        $this->form->fill($this->supportedLanguageSettings([
            ...Setting::all(),
            'languages' => $locales->enabled(),
            'default_language' => $locales->default(),
        ]));
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
                        Select::make('default_language')
                            ->label(__('general.default_language'))
                            ->options(fn (Get $get): array => Arr::only(config('app.locales'), $this->selectedLanguages($get('languages'))))
                            ->searchable()
                            ->selectablePlaceholder(false)
                            ->helperText(__('general.languages_english_fallback')),
                        CheckboxList::make('languages')
                            ->label(__('general.languages'))
                            ->options(config('app.locales'))
                            ->searchable()
                            ->bulkToggleable()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?array $state) {
                                $languages = $this->selectedLanguages($state);

                                if (! in_array($get('default_language'), $languages, true)) {
                                    $set('default_language', $languages[0]);
                                }
                            }),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.data_could_not_saved'))
                        ->action(function (Action $action) {
                            $formData = $this->supportedLanguageSettings($this->form->getState());
                            try {
                                Setting::update($formData);

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ])
                    ->key('saveAction')
                    ->alignLeft(),

            ])
            ->statePath('formData');
    }

    /**
     * @return array<int, string>
     */
    private function selectedLanguages(?array $languages): array
    {
        $languages = array_values(array_intersect(
            $languages ?? [],
            array_keys(config('app.locales'))
        ));

        return $languages ?: ['en'];
    }

    private function supportedLanguageSettings(array $settings): array
    {
        $settings['languages'] = $this->selectedLanguages($settings['languages'] ?? null);

        if (! in_array($settings['default_language'] ?? null, $settings['languages'], true)) {
            $settings['default_language'] = $settings['languages'][0];
        }

        return $settings;
    }
}
