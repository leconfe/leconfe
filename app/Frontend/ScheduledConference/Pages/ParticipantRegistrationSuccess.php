<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Participant;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class ParticipantRegistrationSuccess extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.participant-registration-success';

    public Participant $participant;

    public function mount(Participant $participant)
    {
        //
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => __('general.home'),
            'Participant Registration Success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'participant' => $this->participant,
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get("/{$slug}/{participant:uuid}", static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
