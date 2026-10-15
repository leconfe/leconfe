<?php

namespace App\Frontend\Website\Pages;

use App\Support\Multilingual\ContentLocales;
use App\Support\SafeRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class SetLocale extends Page
{
    public function __invoke(): RedirectResponse
    {
        $locale = Route::getCurrentRoute()?->parameter('lang');

        if (app(ContentLocales::class)->isEnabled($locale)) {
            session()->put('locale', $locale);
            auth()->user()?->rememberPreferredLocale($locale);
        }

        return SafeRedirect::back(request(), $this->getFallbackUrl());
    }

    protected function getFallbackUrl(): string
    {
        if ($scheduledConference = app()->getCurrentScheduledConference()) {
            return $scheduledConference->getHomeUrl();
        }

        if ($conference = app()->getCurrentConference()) {
            return $conference->getHomeUrl();
        }

        return route('livewirePageGroup.website.pages.home');
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();

        Route::get("/{$slug}/{lang}", static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
