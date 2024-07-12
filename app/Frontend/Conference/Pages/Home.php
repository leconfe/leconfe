<?php

namespace App\Frontend\Conference\Pages;

use App\Actions\Conferences\ConferenceUpdateAction;
use App\Models\Topic;
use App\Models\Venue;
use App\Models\Conference;
use App\Models\Submission;
use App\Models\SpeakerRole;
use Illuminate\Support\Str;
use App\Models\Announcement;
use App\Models\CommitteeRole;
use Livewire\Attributes\Title;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;
use Rahmanramsi\LivewirePageGroup\Pages\Page;

class Home extends Page
{
    protected static string $view = 'frontend.conference.pages.home';

    public function mount()
    {
    }

    protected function getViewData(): array
    {
        $additionalInformations = collect(app()->getCurrentConference()->getMeta('additional_information') ?? [])
            ->filter(fn ($tab) => $tab['is_shown'] ?? false)
            ->map(function ($tab) {
                $tab['slug'] = Str::slug($tab['title']);
                return $tab;
            })
            ->values();

        $currentProceeding = app()->getCurrentConference()
            ->proceedings()
            ->published()
            ->current()
            ->first();

        $currentSerie = app()->getCurrentSerie();
        $currentSerie?->load([
            'speakerRoles.speakers' => ['meta'],
        ]);

        return [
            'currentProceeding' => $currentProceeding,
            'currentSerie' => $currentSerie,
            // 'announcements' => Announcement::query()->get(),
            'acceptedSubmission' => app()->getCurrentConference()->submission()->published()->get(),
            'additionalInformations' => $additionalInformations,
            'venues' => Venue::query()->get(),
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
