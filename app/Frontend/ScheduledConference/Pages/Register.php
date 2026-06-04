<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Actions\User\UserCreateAction;
use App\Frontend\ScheduledConference\Pages\Concerns\HasScheduledConferenceAuthLogo;
use App\Frontend\Website\Pages\Page;
use App\Panel\ScheduledConference\Pages\Dashboard;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Squire\Models\Country;

class Register extends Page implements HasActions, HasForms
{
    use HasScheduledConferenceAuthLogo;
    use InteractsWithActions;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use WithRateLimiting;

    protected static string $view = 'frontend.scheduledConference.pages.register';

    protected static string $layout = 'frontend.scheduledConference.components.layout.simple-with-platform-footer';

    public $given_name = null;

    public $family_name = null;

    public $public_name = null;

    public $affiliation = null;

    public $country = null;

    public $email = null;

    public $phone = null;

    public $password = null;

    public $password_confirmation = null;

    public $privacy_statement_agree = false;

    public $registerComplete = false;

    public function mount()
    {
        if (Filament::auth()->check()) {
            $this->redirect($this->getRedirectUrl(), navigate: false);

            return;
        }

        $this->country = app()->getCurrentScheduledConference()->getMeta('default_register_country');
    }

    public function getMaxWidth(): MaxWidth|string|null
    {
        return MaxWidth::FourExtraLarge;
    }

    public static function getLayout(): string
    {
        return static::$layout;
    }

    protected function getLayoutData(): array
    {
        return [
            'maxWidth' => $this->getMaxWidth(),
        ];
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->registerComplete ? __('general.registration_complete') : __('general.register');
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function rules()
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        $rules = [
            'given_name' => [
                $scheduledConference->getMeta('required_given_name') ? 'required' : 'nullable',
            ],
            'family_name' => [
                $scheduledConference->getMeta('required_family_name') ? 'required' : 'nullable',
            ],
            'public_name' => [
                $scheduledConference->getMeta('required_public_name') ? 'required' : 'nullable',
            ],
            'affiliation' => [
                $scheduledConference->getMeta('required_affiliation') ? 'required' : 'nullable',
            ],
            'country' => [
                $scheduledConference->getMeta('required_country') ? 'required' : 'nullable',
            ],
            'phone' => [
                $scheduledConference->getMeta('required_phone') ? 'required' : 'nullable',
                'phone:INTERNATIONAL',
            ],
            'email' => [
                'required',
                'email',
                'indisposable',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'confirmed',
                'min:12',
            ],
            'privacy_statement_agree' => [
                'required',
            ],
        ];

        return $rules;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        $rules = $this->rules();

        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('given_name')
                            ->label(__('general.given_name'))
                            ->rules($rules['given_name'])
                            ->required(in_array('required', $rules['given_name']))
                            ->autofocus()
                            ->columnSpan(1),
                        TextInput::make('family_name')
                            ->label(__('general.family_name'))
                            ->rules($rules['family_name'])
                            ->required(in_array('required', $rules['family_name']))
                            ->columnSpan(1),
                        TextInput::make('public_name')
                            ->label(__('general.public_name'))
                            ->helperText(__('general.public_name_helper'))
                            ->rules($rules['public_name'])
                            ->required(in_array('required', $rules['public_name']))
                            ->columnSpanFull(),
                        TextInput::make('affiliation')
                            ->label(__('general.affiliation'))
                            ->rules($rules['affiliation'])
                            ->required(in_array('required', $rules['affiliation']))
                            ->columnSpan(1),
                        Select::make('country')
                            ->label(__('general.country'))
                            ->options(fn () => Country::all()->pluck('name', 'id'))
                            ->searchable()
                            ->rules($rules['country'])
                            ->required(in_array('required', $rules['country']))
                            ->columnSpan(1),
                        TextInput::make('phone')
                            ->label(__('general.phone'))
                            ->tel()
                            ->rules($rules['phone'])
                            ->required(in_array('required', $rules['phone']))
                            ->helperText(__('general.phone_format_international'))
                            ->columnSpanFull(),
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->rules($rules['email'])
                            ->required(in_array('required', $rules['email']))
                            ->email(in_array('email', $rules['email']))
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label(__('general.password'))
                            ->password()
                            ->revealable()
                            ->rules($rules['password'])
                            ->required(in_array('required', $rules['password']))
                            ->columnSpan(1),
                        TextInput::make('password_confirmation')
                            ->label(__('general.password_confirmation'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->columnSpan(1),
                        Checkbox::make('privacy_statement_agree')
                            ->label(fn () => $this->getPrivacyStatementAgreeLabel())
                            ->rules($rules['privacy_statement_agree'])
                            ->required(in_array('required', $rules['privacy_statement_agree']))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ),
        ];
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    protected function getPrivacyStatementAgreeLabel(): HtmlString
    {
        return new HtmlString(str_replace(
            'class="link link-primary link-hover"',
            'class="fi-simple-link"',
            __('general.privacy_statement_agree', [
                'url' => route(PrivacyStatement::getRouteName()),
            ]),
        ));
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->registerAction(),
            $this->loginAction(),
        ];
    }

    protected function registerAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('general.register'))
            ->submit('register');
    }

    public function areFormActionsSticky(): bool
    {
        return false;
    }

    public function getFormActionsAlignment(): string|Alignment
    {
        return Alignment::Start;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return [];
    }

    public function loginAction(): Action
    {
        return Action::make('register')
            ->link()
            ->label(__('general.login'))
            ->url(app()->getLoginUrl());
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title(__('filament-panels::pages/auth/login.notifications.throttled.title', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]))
            ->body(array_key_exists('body', __('filament-panels::pages/auth/login.notifications.throttled') ?: []) ? __('filament-panels::pages/auth/login.notifications.throttled.body', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => $exception->minutesUntilAvailable,
            ]) : null)
            ->danger();
    }

    public function getRedirectUrl(): string
    {
        return route(Dashboard::getRouteName('scheduledConference'));
    }

    public function register()
    {
        if (! app()->getCurrentScheduledConference()->getMeta('allow_registration')) {
            abort(403);
        }

        try {
            $this->rateLimit(5, 300);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->validate($this->rules());

        try {
            DB::beginTransaction();
            $user = UserCreateAction::run([
                ...Arr::only($data, ['given_name', 'family_name', 'email', 'password']),
                'meta' => Arr::only($data, ['affiliation', 'country', 'phone', 'public_name']),
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        if (config('app.must_verify_email')) {
            $user->sendEmailVerificationNotification();
        }

        Filament::auth()->login($user);

        session()->regenerate();

        $this->redirect($this->getRedirectUrl());

        $this->registerComplete = true;
    }

    protected function getViewData(): array
    {
        $scheduledConference = app()->getCurrentScheduledConference();

        return [
            'loginUrl' => app()->getLoginUrl(),
            'allowRegistration' => $scheduledConference->getMeta('allow_registration'),
            'scheduledConference' => $scheduledConference,
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            $this->getTitle(),
        ];
    }
}
