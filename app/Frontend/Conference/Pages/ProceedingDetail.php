<?php

namespace App\Frontend\Conference\Pages;

use App\Facades\Metric;
use App\Frontend\Conference\Pages\Proceedings as PagesProceedings;
use App\Frontend\Website\Pages\Page;
use App\Models\Enums\SubmissionStatus;
use App\Models\Proceeding;
use App\Models\Track;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class ProceedingDetail extends Page
{
    protected static string $view = 'frontend.conference.pages.proceeding-detail';

    public Proceeding $proceeding;

    public function getTitle(): string|Htmlable
    {
        return $this->proceeding->seriesTitle();
    }

    public function mount(Proceeding $proceeding)
    {
        abort_unless($this->canAccess(), 404);

        Metric::log('proceeding_view', $proceeding);
    }

    public function canAccess(): bool
    {
        return $this->proceeding->isPublished();
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            route(PagesProceedings::getRouteName()) => 'Proceedings',
            Str::limit($this->proceeding->seriesTitle(), 120),
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/proceedings/view/{proceeding}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }

    public function getViewData(): array
    {
        $tracks = Track::query()
            ->orderBy('order_column')
            ->with(['submissions' => fn ($query) => $query
                ->where('proceeding_id', $this->proceeding->id)
                ->where('status', SubmissionStatus::Published)
                ->with(['authors', 'doi', 'galleys.file.media', 'meta'])])
            ->whereHas('submissions', fn ($query) => $query
                ->where('proceeding_id', $this->proceeding->id)
                ->where('status', SubmissionStatus::Published))
            ->get();

        return [
            'proceeding' => $this->proceeding,
            'tracks' => $tracks,
        ];
    }
}
