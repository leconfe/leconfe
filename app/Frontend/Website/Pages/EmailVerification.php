<?php

namespace App\Frontend\Website\Pages;

use App\Facades\Setting;
use Livewire\Attributes\Title;
use Rahmanramsi\LivewirePageGroup\Pages\Page;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;

class EmailVerification extends Page
{
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.email-verification';

    public function mount()
    {
        if (! config('app.must_verify_email')) {
            return redirect()->route('livewirePageGroup.website.pages.home');
        }

        if (! auth()->check()) {
            return redirect()->route('livewirePageGroup.website.pages.login');
        }

        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('livewirePageGroup.website.pages.home');
        }
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            'Email Verification',
        ];
    }

    public function sendEmailVerificationLink()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->route('livewirePageGroup.website.pages.home');
        }

        try {
            $this->rateLimit(1);
        } catch (TooManyRequestsException $exception) {
            $this->addError('email', __('general.throttle_please_try_again', [
                'seconds' => $exception->secondsUntilAvailable,
            ]));

            return null;
        }

        auth()->user()->sendEmailVerificationNotification();

        session()->flash('success', true);
    }
}
