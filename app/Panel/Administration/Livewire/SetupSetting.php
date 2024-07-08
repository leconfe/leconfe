<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Site\SiteUpdateAction;
use App\Forms\Components\CssFileUpload;
use App\Models\Conference;
use App\Models\Site;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SetupSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public Site $site;

    public ?array $formData = [];

    public function mount(Conference $conference): void
    {
        $this->site = App::getSite();

        $this->form->fill([
            'meta' => $this->site->getAllMeta()->toArray(),
        ]);
    }

    public function render()
    {
        return view('panel.administration.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->model($this->site)
            ->schema([
                Section::make()
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('favicon')
                            ->label(__('translation.setupSettingAdmin.labelFavicon'))
                            ->collection('favicon')
                            ->image()
                            ->conversion('thumb')
                            ->columnSpan([
                                'xl' => 1,
                                'sm' => 2,
                            ]),
                        ColorPicker::make('meta.appearance_color')
                            ->label(__('translation.setupSettingAdmin.labelAppearanceColor')),
                        CssFileUpload::make('styleSheet')
                            ->label(__('translation.setupSettingAdmin.labelCustomStylesheet'))
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
                        ->successNotificationTitle(__('translation.setupSettingAdmin.successNotificationTitle'))
                        ->failureNotificationTitle(__('translation.setupSettingAdmin.failureNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                SiteUpdateAction::run($formData);
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
