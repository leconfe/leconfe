<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Site\SiteUpdateAction;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Mohamedsabil83\FilamentFormsTinyeditor\Components\TinyEditor;
use Stevebauman\Purify\Facades\Purify;

class InformationSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount()
    {
        $this->form->fill([
            'meta' => app()->getSite()->getAllMeta()->toArray(),
        ]);
    }

    public function render()
    {
        return view('panel.administration.livewire.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('meta.name')
                            ->label(__('translation.informationSettingAdmin.labelWebsitename'))
                            ->required(),
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->label(__('translation.informationSettingAdmin.labelLogo'))
                            ->collection('logo')
                            ->model(app()->getSite())
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb')
                            ->columnSpan([
                                'sm' => 2,
                            ]),
                        Textarea::make('meta.description')
                            ->label(__('translation.informationSettingAdmin.labelDescription'))
                            ->rows(3)
                            ->autosize()
                            ->columnSpanFull()
                            ->hint(__('translation.informationSettingAdmin.hintRecommendedLength:50-160chara'))
                            ->helperText(__('translation.informationSettingAdmin.helperTextAShortDescritionOfTheWebsite')),
                        TinyEditor::make('meta.about')
                            ->label(__('translation.informationSettingAdmin.labelAboutSite'))
                            ->minHeight(300)
                            ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state))
                            ->columnSpan([
                                'sm' => 2,
                            ]),
                        TinyEditor::make('meta.page_footer')
                            ->label(__('translation.informationSettingAdmin.labelPageFooter'))
                            ->minHeight(300)
                            ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state))
                            ->columnSpan([
                                'sm' => 2,
                            ]),
                    ])
                    ->columns(2),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.informationSettingAdmin.successNotificationTitleSaved'))
                        ->failureNotificationTitle(__('translation.informationSettingAdmin.failureNotificationTitleFailed'))
                        ->action(function (Action $action) {
                            $data = $this->form->getState();
                            try {
                                SiteUpdateAction::run($data);
                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ]),
            ])
            ->statePath('formData');
    }
}
