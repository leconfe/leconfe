<?php

namespace App\Frontend\Website\Pages;

use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class EmailVerification extends Page
{
    use WithRateLimiting;

    protected static string $view = 'frontend.website.pages.email-verification';

    public function __invoke()
    {
        $request = request();

        if ($request->route('id') && $request->route('hash')) {
            $this->fulfillEmailVerification($request);

            return redirect()->to($this->getContextRoute('home'));
        }

        return parent::__invoke();
    }

    public function mount()
    {
        if (! config('app.must_verify_email')) {
            return redirect()->to($this->getContextRoute('home'));
        }

        if (! auth()->check()) {
            return redirect()->to($this->getContextRoute('login'));
        }

        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->to($this->getContextRoute('home'));
        }
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get("/{$slug}", static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));

        Route::get('email/verify/{id}/{hash}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->middleware(['auth', 'signed'])
            ->name('verification.verify');
    }

    public function getBreadcrumbs(): array
    {
        return [
            $this->getContextRoute('home') => __('general.home'),
            'Email Verification',
        ];
    }

    public function sendEmailVerificationLink()
    {
        if (auth()->user()->hasVerifiedEmail()) {
            return redirect()->to($this->getContextRoute('home'));
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

    protected function getContextRoute(string $page): string
    {
        return route($this->getContextRouteName($page), $this->getContextRouteParameters());
    }

    protected function getContextRouteName(string $page): string
    {
        if (app()->getCurrentScheduledConference()) {
            return "livewirePageGroup.scheduledConference.pages.$page";
        }

        if (app()->getCurrentConference()) {
            return "livewirePageGroup.conference.pages.$page";
        }

        return "livewirePageGroup.website.pages.$page";
    }

    protected function getContextRouteParameters(): array
    {
        if ($scheduledConference = app()->getCurrentScheduledConference()) {
            return [
                'conference' => $scheduledConference->conference->path,
                'serie' => $scheduledConference->path,
            ];
        }

        if ($conference = app()->getCurrentConference()) {
            return [
                'conference' => $conference->path,
            ];
        }

        return [];
    }

    protected function fulfillEmailVerification(Request $request): void
    {
        $user = $request->user();
        $id = (string) $request->route('id');
        $hash = (string) $request->route('hash');

        abort_unless($user, 403);

        if (! hash_equals((string) $user->getKey(), $id)) {
            abort(403, 'Invalid or expired signature');
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Invalid or expired signature');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }
    }
}
