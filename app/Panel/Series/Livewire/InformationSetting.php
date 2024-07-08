<?php

namespace App\Panel\Series\Livewire;


use App\Actions\Conferences\ConferenceUpdateAction;
use App\Actions\Series\SerieUpdateAction;
use App\Models\Enums\SerieType;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
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

    public ?array $formData = [];

    public function mount(): void
    {
        $this->form->fill([
            ...app()->getCurrentSerie()->attributesToArray(),
            'meta' => app()->getCurrentSerie()->getAllMeta(),
        ]);
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model(app()->getCurrentSerie())
            ->schema([
                Section::make()
                    ->columns(1)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('translation.serieSetting.informationSettingLabelSerieTitle'))
                            ->autofocus()
                            ->autocomplete()
                            ->required()
                            ->placeholder(__('translation.serieSetting.informationSettingPlaceHolderEnterSerie')),
                        TextInput::make('issn')
                            ->label(__('translation.serieSetting.informationSettingLabelIssn'))
                            ->placeholder(__('translation.serieSetting.informationSettingPlaceHolderIssn')),
                        Grid::make([
                            'xl' => 2
                        ])
                            ->schema([
                                SpatieMediaLibraryFileUpload::make('thumbnail')
                                    ->label(__('translation.serieSetting.informationSettingLabelThumbnail'))
                                    ->collection('thumbnail')
                                    ->helperText(__('translation.serieSetting.informationSettingHelperTextAnImageRepresentation'))
                                    ->image()
                                    ->conversion('thumb'),
                                SpatieMediaLibraryFileUpload::make('cover')
                                    ->label(__('translation.serieSetting.informationSettingLabelCover'))
                                    ->collection('cover')
                                    ->helperText(__('translation.serieSetting.informationSettingHelperTextCoverImageForTheSerie'))
                                    ->image()
                                    ->conversion('thumb'),
                            ]),
                        Grid::make()
                            ->schema([
                                DatePicker::make('date_start')
                                    ->label(__('translation.serieSetting.informationSettingLabelStartDate'))
                                    ->placeholder(__('translation.serieSetting.informationSettingPlaceHolderEnterThStart'))
                                    ->requiredWith('date_end'),
                                DatePicker::make('date_end')
                                    ->label(__('translation.serieSetting.informationSettingLabelEndDate'))
                                    ->afterOrEqual('date_start')
                                    ->requiredWith('date_start')
                                    ->placeholder(__('translation.serieSetting.informationSettingPlaceHolderEnterTheEnd')),
                            ]),
                        Select::make('type')
                            ->label(__('translation.serieSetting.informationSettingLabelType'))
                            ->required()
                            ->options(SerieType::array()),
                        TinyEditor::make('meta.about')
                            ->label(__('translation.serieSetting.informationSettingLabelAboutSerie'))
                            ->minHeight(300),
                        TinyEditor::make('meta.additional_content')
                            ->label(__('translation.serieSetting.informationSettingLabelAdditionalContent'))
                            ->minHeight(300),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.serieSetting.informationSettingSuccessNotificationTitle'))
                        ->failureNotificationTitle(__('translation.serieSetting.informationSettingFailureNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                SerieUpdateAction::run(app()->getCurrentSerie(), $formData);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                                throw $th;
                            }
                        }),
                ])->alignLeft(),
            ])
            ->statePath('formData');
    }
}
