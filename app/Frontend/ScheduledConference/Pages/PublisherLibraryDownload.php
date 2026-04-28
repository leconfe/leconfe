<?php

namespace App\Frontend\ScheduledConference\Pages;

use App\Frontend\Website\Pages\Page;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class PublisherLibraryDownload extends Page
{
    protected static string $view = 'frontend.scheduledConference.pages.publisher-library';

    public function mount()
    {
        //
    }

    public function __invoke()
    {
        $currentRoute = Route::getCurrentRoute();

        $mediaUuid = $currentRoute->parameter('media');

        if (! $mediaUuid) {
            abort(404);
        }

        $media = app()->getCurrentScheduledConference()->media()->where('uuid', $mediaUuid)->first();
        if (! $media || ! $media->getCustomProperty('is_public')) {
            abort(404);
        }

        return response()->download($media->getPath(), str_replace(['/', '\\'], '-', $media->originalFileName));
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/publisher-library/download/{media}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
