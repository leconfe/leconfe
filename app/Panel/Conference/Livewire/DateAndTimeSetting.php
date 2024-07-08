<?php

namespace App\Panel\Conference\Livewire;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Actions\Settings\SettingUpdateAction;
use App\Actions\Site\SiteUpdateAction;
use App\Facades\Setting;
use Livewire\Component;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Section;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Get;

class DateAndTimeSetting extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $formData = [];

    public function mount()
    {
        $this->form->fill(Setting::all());
    }

    public function render()
    {
        return view('panel.conference.livewire.form');
    }

    public function form(Form $form): Form
    {
        $now = now()->hours(16);
        return $form
            ->statePath('formData')
            ->schema([
                Section::make(__('translation.dateTimeSetting.titleDateAndTime'))
                ->description(new HtmlString(
                    __('translation.dateTimeSetting.descriptionDateAndTime') . ' <a href="https://www.php.net/manual/en/function.strftime.php#refsect1-function.strftime-parameters" target="_blank"
                    class="filament-link inline-flex items-center justify-center gap-0.5 font-medium outline-none hover:underline focus:underline text-sm text-primary-600 hover:text-primary-500 filament-tables-link-action">' . __('translation.dateTimeSetting.descriptionFormatCharacters') . '</a>.'
                    ))
                    ->schema([
                        Radio::make('select_format_date')
                            ->label(__('translation.dateTimeSetting.labeldate'))
                            ->options(
                                fn () => collect([
                                    'F j, Y',
                                    'F j Y',
                                    'j F Y',
                                    'Y F j',
                                ])
                                    ->mapWithKeys(fn ($format) => [$format => $now->format($format)])
                                    ->put('custom', __('translation.dateTimeSetting.labelcustom'))
                            ),
                        TextInput::make('format_date')
                            ->hiddenLabel()
                            ->placeholder(__('translation.dateTimeSetting.placeholderEnterCustomDateFormat'))
                            ->required(fn (Get $get) => $get('select_format_date') === 'custom')
                            ->dehydrated()
                            ->dehydrateStateUsing(fn (Get $get, ?string $state) => $get('select_format_date') === 'custom' ? $state : $get('select_format_date')),
                        Radio::make('select_format_time')
                            ->label(__('translation.dateTimeSetting.labeltime'))
                            ->options(
                                fn () => collect([
                                    'h:i A',
                                    'g:ia',
                                    'H:i',
                                ])
                                    ->mapWithKeys(fn ($format) => [$format => $now->format($format)])
                                    ->put('custom', __('translation.dateTimeSetting.labelcustom'))
                            ),
                        TextInput::make('format_time')
                            ->hiddenLabel()
                            ->placeholder(__('dateTimeSetting.placehdcs'))
                            ->required(fn (Get $get) => $get('select_format_time') === 'custom')
                            ->dehydrated()
                            ->dehydrateStateUsing(fn (Get $get, ?string $state) => $get('select_format_time') === 'custom' ? $state : $get('select_format_time')),
                    ]),
                Actions::make([
                    Action::make('save')
                        ->label(__('translation.button.save'))
                        ->successNotificationTitle(__('translation.dateTimeSetting.successNotificationTitle'))
                        ->action(function (Action $action) {
                            $formData = $this->form->getState();
                            try {
                                Setting::update($formData);

                                $action->sendSuccessNotification();
                            } catch (\Throwable $th) {
                                $action->sendFailureNotification();
                            }
                        }),
                ])->alignLeft(),
            ]);
    }
}
