<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Events\UserLoggedIn;
use App\Frontend\ScheduledConference\Pages\Concerns\HasScheduledConferenceAuthLogo;
use App\Frontend\Website\Pages\Login as WebsiteLogin;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class Login extends WebsiteLogin implements HasForms, HasActions
{
    use HasScheduledConferenceAuthLogo;
    use InteractsWithActions;
    use InteractsWithFormActions;
    use InteractsWithForms;

    protected static string $view = 'frontend.scheduledConference.pages.login';

    protected static string $layout = 'filament-panels::components.layout.simple';

    public static function getLayout(): string
    {
        return static::$layout;
    }

    /**
     * @return array<string>
     */
    public function getRenderHookScopes(): array
    {
        return [static::class];
    }

    public function getViewData(): array
    {
        return [
            'resetPasswordUrl' => route('livewirePageGroup.scheduledConference.pages.reset-password'),
            'registerUrl' => route('livewirePageGroup.scheduledConference.pages.register'),
        ];
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        TextInput::make('email')
                            ->label(__('general.email'))
                            ->email()
                            ->required()
                            ->autofocus()
                            ->autocomplete('username')
                            ->columnSpanFull(),
                        TextInput::make('password')
                            ->label(__('general.password'))
                            ->password()
                            ->required()
                            ->autocomplete('current-password')
                            ->columnSpanFull(),
                        Checkbox::make('remember')
                            ->label(__('general.remember_me'))
                            ->columnSpanFull(),
                    ]),
            ),
        ];
    }

    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<Action | ActionGroup>
     */
    protected function getFormActions(): array
    {
        return [
            $this->getAuthenticateFormAction(),
            $this->registerAction(),
        ];
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(__('general.login'))
            ->submit('login');
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

    public function registerAction(): Action
    {
        return Action::make('register')
            ->link()
            ->label(__('general.register'))
            ->url(route('livewirePageGroup.scheduledConference.pages.register'));
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

    public function login()
    {
        try {
            $this->rateLimit(5, 300);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $this->validate();

        if (
            !auth()->attempt([
                'email' => $this->email,
                'password' => $this->password,
            ], $this->remember)
        ) {
            throw ValidationException::withMessages([
                'email' => __('general.failed_credentials'),
            ]);
        }

        $this->clearRateLimiter();

        session()->regenerate();

        $user = auth()->user();
        $user->setMeta('last_login', now());

        UserLoggedIn::dispatch($user);

        $this->redirectIntended($this->getRedirectUrl(), navigate: false);
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            __('general.login'),
        ];
    }

    public function getRedirectUrl(): string
    {
        return route('filament.scheduledConference.pages.dashboard');
    }
}
