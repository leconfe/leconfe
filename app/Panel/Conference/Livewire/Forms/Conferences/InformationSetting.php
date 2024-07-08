<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Livewire\Component;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;

class InformationSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public Conference $conference;

    public ?array $formData = [];

    public function mount(Conference $conference): void
    {
        $this->form->fill([
            ...$conference->attributesToArray(),
            'meta' => $conference->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->conference)
            ->schema([
                Section::make()
                    ->columns(1)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('translation.informationSetting.informationSettingLabelName'))
                            ->columnSpanFull()
                            ->required(),
                        TextInput::make('meta.acronym')
                            ->unique(column: 'path', ignorable: $this->conference)
                            ->label(__('translation.informationSetting.informationSettingLabelAcronym'))
                            ->rule('alpha_dash')
                            ->live(onBlur: true),
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->label(__('translation.informationSetting.informationSettingLabelLogo'))
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb'),
                        TextInput::make('meta.theme')
                            ->label(__('translation.informationSetting.informationSettingLabelTheme'))
                            ->placeholder(__('translation.informationSetting.informationSettingPlaceholderTheme'))
                            ->helperText(__('translation.informationSetting.informationSettingHelperTextTheme'))
                            ->columnSpanFull(),
                        Textarea::make('meta.description')
                            ->label(__('translation.informationSetting.informationSettingLabelDescription'))
                            ->hint(__('translation.informationSetting.informationSettingHintDescription'))
                            ->helperText(__('translation.informationSetting.informationSettingHelperTextDescription'))
                            ->maxLength(255)
                            ->autosize(),
                        TinyEditor::make('meta.page_footer')
                            ->label(__('translation.informationSetting.informationSettingLabelPageFooter'))
                            ->minHeight(300),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.informationSetting.informationSettingSuccessNotificationTitle'))
                        ->failureNotificationTitle(__('translation.informationSetting.informationSettingFailureNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                ConferenceUpdateAction::run($this->conference, $formData);
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
