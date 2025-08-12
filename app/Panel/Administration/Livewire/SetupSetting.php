<?php

namespace App\Panel\Administration\Livewire;

use App\Actions\Site\SiteUpdateAction;
use App\Filament\Forms\Components\MultilanguageComponent;
use App\Forms\Components\TinyEditor;
use App\Models\Conference;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Livewire\Component;
use Squire\Models\Country;
use Stevebauman\Purify\Facades\Purify;

class SetupSetting extends Component implements HasForms
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
        return view('forms.form');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        MultilanguageComponent::make([
                            TextInput::make('meta.name')
                            ->label(__('general.website_name'))
                            ->required(),
                        ]),
                        
                        Select::make('meta.conference_redirect')
                            ->label(__('general.conference_redirect'))
                            ->helperText(__('general.conference_redirect_hint'))
                            ->options(Conference::all()->mapWithKeys(fn ($conference) => [$conference->id => $conference->getLocalizedMeta('name')])->filter()),
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->label(__('general.logo'))
                            ->model(app()->getSite())
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb'),
                        SpatieMediaLibraryFileUpload::make('favicon')
                            ->collection('favicon')
                            ->label('Favicon')
                            ->model(app()->getSite())
                            ->image()
                            ->imageResizeUpscale(false)
                            ->conversion('thumb'),
                        MultilanguageComponent::make([
                            Textarea::make('meta.description')
                            ->label(__('general.description'))
                            ->rows(3)
                            ->autosize()
                            ->hint(__('general.recommended_description_length'))
                            ->helperText(__('general.short_description_of_the_website')),
                        ]),
                        
                        Section::make(__('general.publishing_details'))
                            ->description(__('general.publishing_detail_included_in_metadata'))
                            ->schema([
                                Select::make('meta.publisher_location')
                                    ->label(__('general.country'))
                                    ->placeholder(__('general.select_a_country'))
                                    ->searchable()
                                    ->options(fn () => Country::all()->mapWithKeys(fn ($country) => [$country->name => $country->flag.' '.$country->name]))
                                    ->optionsLimit(250),
                                TextInput::make('meta.publisher_name')
                                    ->label(__('general.publisher'))
                                    ->required(),
                                TextInput::make('meta.publisher_url')
                                    ->url()
                                    ->validationMessages([
                                        'url' => __('general.url_must_be_valid'),
                                    ])
                                    ->label(__('general.url')),
                            ]),
                        MultilanguageComponent::make([
                            TinyEditor::make('meta.about')
                            ->label(__('general.about_site'))
                            ->profile('advanced')
                            ->minHeight(300)
                            ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                            TinyEditor::make('meta.page_footer')
                            ->label(__('general.page_footer'))
                            ->toolbar('bold italic superscript subscript | link | blockquote bullist numlist | image | code')
                            ->plugins('paste link lists image code')
                            ->minHeight(300)
                            ->dehydrateStateUsing(fn (?string $state) => Purify::clean($state)),
                        ]),
                        
                    ])
                    ->columns(1),
                Actions::make([
                    Action::make('save')
                        ->label(__('general.save'))
                        ->successNotificationTitle(__('general.saved'))
                        ->failureNotificationTitle(__('general.failed'))
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
