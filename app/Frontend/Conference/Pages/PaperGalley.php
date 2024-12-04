<?php

namespace App\Frontend\Conference\Pages;

use App\Facades\Hook;
use App\Facades\Metric;
use App\Frontend\Website\Pages\Page;
use App\Models\Enums\SubmissionStatus;
use App\Models\Media;
use App\Models\Submission;
use Illuminate\Support\Facades\Route;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class PaperGalley extends Page
{
    public function __invoke()
    {
        $currentRoute = Route::getCurrentRoute();

        $submission = Submission::query()
            ->where('status', SubmissionStatus::Published)
            ->where('id', $currentRoute->parameter('submission'))
            ->first();

        abort_if(! $submission, 404);

        $galley = $submission->galleys()->where('id', $currentRoute->parameter('galley'))->first();

        abort_if(! $galley, 404);

        $media = Media::findByUuid($galley->file->media->uuid);

        abort_if(! $media, 404);

        $returner = null;

        Metric::log('galley_view', $galley);

        Hook::call('Frontend::PaperGalley', [$galley, &$returner]);

        if (! $returner) {
            return redirect()->route(PaperGalleyDownload::getRouteName(), [
                'submission' => $submission->id,
                'galley' => $galley->id,
            ]);
        }


        return $returner;
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/paper/view/{submission}/{galley}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
