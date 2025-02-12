<?php

namespace App\Frontend\Website\Pages;

use App\Events\UserLoggedIn;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Rule;

class Login extends Page
{
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.login';

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

    public function getTitle(): string|Htmlable
    {
        return __('general.login');
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
