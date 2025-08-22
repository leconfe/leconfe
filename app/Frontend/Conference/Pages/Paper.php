<?php

namespace App\Frontend\Conference\Pages;

use App\Facades\Citation;
use App\Facades\Hook;
use App\Facades\License;
use App\Facades\MetaTag;
use App\Frontend\Website\Pages\Page;
use App\Models\Submission;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Paper extends Page
{
    protected static string $view = 'frontend.conference.pages.paper';

    public ?Submission $paper;

    public function mount($submission)
    {
        $this->paper = Submission::query()
            ->where('id', $submission)
            ->with(['proceeding', 'track', 'media', 'meta', 'galleys.file.media', 'authors' => fn($query) => $query->with(['role', 'meta'])])
            ->first();

        if (! $this->paper) {
            return abort(404);
        }

        if ($this->paper->isPublishedOnExternal()) {
            return redirect($this->paper->getPublicUrl());
        }

        if (! $this->canAccess()) {
            abort(404);
        }

        $this->addHead();
        $this->addMetadata();
    }

    public function getViewData(): array
    {
        return [
            'ccLicenseBadge' => License::getCCLicenseBadge($this->paper->getMeta('license_url'), app()->getLocale()),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->paper->getMeta('title');
    }

    public function addHead()
    {
        Hook::add('Frontend::Views::Head', function ($hookName, &$output) {
            if($licenseUrl = $this->paper->getMeta('license_url')){
                $output .= '<link rel="license" href="'. $licenseUrl .'">';
            }
        });
    }

    public function addMetadata(): void
    {
        $conference = $this->paper->conference;

        MetaTag::add(property: 'og:site_name', content: e($conference->name));
        MetaTag::add(property: 'og:description', content: e($conference->getMeta('description')));
        MetaTag::add(property: 'og:title', content: e($this->paper->getMeta('title')));
        MetaTag::add(property: 'og:type', content: 'paper');
        MetaTag::add(property: 'og:url', content: route(static::getRouteName(), ['submission' => $this->paper->getKey()]));
        if ($this->paper->getFirstMedia('cover')) {
            MetaTag::add(property: 'og:image', content: $this->paper->getFirstMedia('cover')->getAvailableUrl(['thumb']));
        }

        Hook::call('Frontend::Paper::addMetadata', [$this, $this->paper]);
    }

    public function canAccess(): bool
    {
        if (! $this->paper->proceeding) {
            return false;
        }

        if (auth()->user()?->can('preview', $this->paper)) {
            return true;
        }

        if ($this->paper->isPublished() && $this->paper->proceeding->isPublished()) {
            return true;
        }

        return false;
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            route(Proceedings::getRouteName()) => 'Proceedings',
            route(ProceedingDetail::getRouteName(), [$this->paper->proceeding->id]) => Str::limit(
                $this->paper->proceeding->seriesTitle(),
                70
            ),
            'Paper',
        ];
    }

    public static function routes(PageGroup $pageGroup): void
    {
        $slug = static::getSlug();
        Route::get('/paper/view/{submission}', static::class)
            ->middleware(static::getRouteMiddleware($pageGroup))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($pageGroup))
            ->name((string) str($slug)->replace('/', '.'));
    }
}
