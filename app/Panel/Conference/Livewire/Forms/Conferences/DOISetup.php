<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class DOISetup extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            'meta' => app()->getCurrentConference()->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentConference())
            ->schema([
                Section::make()
                    ->schema([
                        Fieldset::make('DOIs')
                            ->schema([
                                Checkbox::make('meta.doi_enabled')
                                    ->label(__('translation.doiSetup.labelAllowDigital'))
                            ])
                            ->columns(1),
                        Fieldset::make(__('translation.doiSetup.fieldsetItemsWithDOIs'))
                            ->schema([
                                Placeholder::make('items.description')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(__('translation.doiSetup.contentItemsWithDOIs'))),
                                CheckboxList::make('meta.doi_items')
                                    ->hiddenLabel()
                                    ->options([
                                        'articles' => __('translation.doiSetup.optionsArticles'),
                                    ])
                            ])
                            ->columns(1),
                        TextInput::make('meta.doi_prefix')
                            ->label(__('translation.doiSetup.labelDOIPrefix'))
                            // ->maxWidth(MaxWidth::Small)
                            ->helperText(new HtmlString(__('translation.doiSetup.helperTextDOIPrefix')))
                            ->placeholder('10.xxxxx')
                            ->regex('/^10\.\d+$/')
                            ->requiredUnless('meta.doi_enabled', true)
                            ->validationMessages([
                                'regex' => __('translation.doiSetup.validationMessagesRegex'),
                                'required_unless' => __('translation.doiSetup.validationMessagesRequiredUnless')
                            ]),
                        Select::make('meta.doi_automatic_assignment')
                            ->label(__('translation.doiSetup.labelAutomaticDOIAssignment'))
                            ->helperText(new HtmlString(__('translation.doiSetup.helperTextAutomaticDOIAssignment')))
                            ->placeholder(__('translation.doiSetup.placeholderAutomaticDOIAssignment'))
                            ->options([
                                'edit_stage' => __('translation.doiSetup.optionsEditStage'),
                                'published' => __('translation.doiSetup.optionsPublished'),
                            ]),
                        Fieldset::make(__('translation.doiSetup.doiFormat'))
                            ->schema([
                                Radio::make('meta.doi_format')
                                    ->hiddenLabel()
                                    ->options([
                                        'default' => __('translation.doiSetup.optionsDOIFormatDefault'),
                                        'none' => __('translation.doiSetup.optionsDOIFormatNone')
                                    ])
                            ])
                            ->columns(1)
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.doiSetup.successNotificationTitleDOISetup'))
                        ->failureNotificationTitle(__('translation.doiSetup.failureNotificationTitleDOISetup'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                ConferenceUpdateAction::run(app()->getCurrentConference(), $formData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                throw $th;
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
