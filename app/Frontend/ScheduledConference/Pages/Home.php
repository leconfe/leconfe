<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use App\Models\Stakeholder;
use App\Models\StakeholderLevel;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.home';

    public function mount() {}

    protected function getViewData(): array
    {
        $currentScheduledConference = app()->getCurrentScheduledConference();
        $currentScheduledConference->load([
            'speakerRoles' => fn($query) => $query->ordered()->with([
                'speakers' => fn($query) => $query->ordered()->with('meta'),
            ]),
        ]);

        $sponsorLevels = StakeholderLevel::sponsors()
            ->with(['stakeholders'])
            ->whereHas('stakeholders')
            ->orderBy('order_column', 'asc')
            ->get();

        $sponsorsWithoutLevel = Stakeholder::sponsors()
            ->whereNull('level_id')
            ->orderBy('order_column', 'asc')
            ->get();

        $partners = Stakeholder::partners()
            ->where('is_shown', true)
            ->orderBy('order_column', 'asc')
            ->get();

        return [
            'partners' => $partners,
            'sponsorLevels' => $sponsorLevels,
            'sponsorsWithoutLevel' => $sponsorsWithoutLevel,
            'currentScheduledConference' => $currentScheduledConference,
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
