<?php

namespace App\Panel\Conference\Livewire\Forms\Conferences;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Forms\Components\CssFileUpload;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SetupSetting extends Component implements HasForms
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
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('favicon')
                            ->collection('favicon')
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb')
                            ->columnSpan([
                                'xl' => 1,
                                'sm' => 2,
                            ]),
                        ColorPicker::make('meta.appearance_color')
                            ->label(__('translation.setupSetting.setupSettingLabelAppearanceColor')),
                        CssFileUpload::make('styleSheet')
                            ->label(__('translation.setupSetting.setupSettingLabelCustomStylesheet'))
                            ->collection('styleSheet')
                            ->getUploadedFileNameForStorageUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file) {
                                return Str::random().'.css';
                            })
                            ->acceptedFileTypes(['text/css'])
                            ->columnSpan([
                                'xl' => 1,
                                'sm' => 2,
                            ]),

                    ]),

                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.setupSetting.setupSettingSuccessNotificationTitle'))
                        ->failureNotificationTitle(__('translation.setupSetting.setupSettingFailureNotificationTitle'))
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
