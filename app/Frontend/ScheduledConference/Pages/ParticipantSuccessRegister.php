<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Participant;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class ParticipantSuccessRegister extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.participant-success-register';

    public function mount()
    {
        //
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            'Participant Success Register',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
         
        ];
    }

    // public static function routes(PageGroup $pageGroup): void
    // {
    //     $slug = static::getSlug();
    //     Route::get("/{$slug}/{payment}", static::class)
    //         ->middleware(static::getRouteMiddleware($pageGroup))
    //         ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
    //         ->name((string) str($slug)->replace('/', '.'));
    // }
}
