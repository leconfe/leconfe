<?php

namespace App\Frontend\Website\Pages;

use App\Events\UserLoggedIn;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Vite;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;

class Login extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithFormActions;
    use InteractsWithForms;
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.login';

    protected static string $layout = 'frontend.scheduledConference.components.layout.simple-with-platform-footer';

    #[Rule('required|email')]
    public ?string $email = null;

    #[Rule('required')]
    public ?string $password = null;

    #[Rule('boolean')]
    public bool $remember = false;

    public function mount()
    {
        // dd(url()->previous());

        if (auth()->check()) {
            $this->redirect($this->getRedirectUrl(), navigate: false);
        }
    }

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

    public function getTitle(): string|Htmlable
    {
        return __('general.login');
    }

    public function getHeading(): string|Htmlable
    {
        return $this->getTitle();
    }

    public function getAuthLogoUrl(): string
    {
        return Vite::asset('resources/assets/images/logo.png');
    }

    public function getAuthLogoAltText(): string
    {
        return config('app.name', 'Leconfe');
    }

    public function getAuthLogoHomeUrl(): string
    {
        return url('/');
    }

    public function getRedirectUrl(): string
    {
        return route('filament.administration.home');
    }

    public function getViewData(): array
    {
        return [
            'registerUrl' => null,
            'resetPasswordUrl' => route('livewirePageGroup.website.pages.reset-password'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            url('/') => __('general.home'),
            __('general.login'),
        ];
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
                            ->revealable()
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

    public function login()
    {
        try {
            $this->rateLimit(5, 300);
        } catch (TooManyRequestsException $exception) {
            $this->addError('throttle', __('general.throttle_to_many_login_attempts', [
                'seconds' => $exception->secondsUntilAvailable,
                'minutes' => ceil($exception->secondsUntilAvailable / 60),
            ]));

            return null;
        }

        $this->validate();

        if (! auth()->attempt([
            'email' => $this->email,
            'password' => $this->password,
        ], $this->remember)) {
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
}
