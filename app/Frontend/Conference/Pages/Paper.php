<?php

namespace App\Frontend\Conference\Pages;

use App\Facades\Hook;
use App\Facades\License;
use App\Facades\MetaTag;
use App\Facades\Metric;
use App\Frontend\Website\Pages\Page;
use App\Models\Submission;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
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

        $this->addMetadata();

        Metric::log('abstract_view', $this->paper);
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

    public function addMetadata(): void
    {
        $conference = $this->paper->conference;
        $scheduledConference = $this->paper->scheduledConference;

        MetaTag::add('gs_meta_revision', '1.1');
        MetaTag::add('citation_title', e($this->paper->getMeta('title')));

        $this->paper->authors->each(function ($author) {
            MetaTag::add('citation_author', $author->fullName);
            if ($author->getMeta('affiliation')) {
                MetaTag::add('citation_author_affiliation', e($author->getMeta('affiliation')));
            }
        });

        if ($this->paper->isPublished()) {
            MetaTag::add('citation_publication_date', $this->paper->published_at?->format('Y/m/d'));
            MetaTag::add('citation_date', $this->paper->published_at?->format('Y/m/d'));
        }

        if ($this->paper->doi?->doi) {
            MetaTag::add('citation_doi', $this->paper->doi->doi);
        }

        if ($scheduledConference->getMeta('publisher_name')) {
            MetaTag::add('citation_publisher', e($scheduledConference->getMeta('publisher_name')));
        }

        $proceeding = $this->paper->proceeding;

        MetaTag::add('citation_conference_title', e($conference->name));
        if ($conference->getMeta('issn')) {
            MetaTag::add('citation_issn', e($conference->getMeta('issn')));
        }
        MetaTag::add('citation_volume', e($proceeding->volume));
        MetaTag::add('citation_issue', e($proceeding->number));
        if ($this->paper) {
            MetaTag::add('citation_section', e($this->paper->track->title));
        }

        if ($this->paper->getMeta('article_pages')) {
            [$start, $end] = explode('-', $this->paper->getMeta('article_pages'));

            if ($start) {
                MetaTag::add('citation_firstpage', $start);
            }

            if ($end) {
                MetaTag::add('citation_lastpage', $end);
            }
        }

        MetaTag::add('citation_abstract_html_url', route(static::getRouteName(), ['submission' => $this->paper->getKey()]));

        $this->paper->galleys->each(function ($galley) {
            if ($galley->isPdf()) {
                MetaTag::add('citation_pdf_url', $galley->getUrl());
            }
        });

        collect($this->paper->getMeta('keywords'))
            ->each(fn($keyword) => MetaTag::add('citation_keywords', $keyword));

        collect(explode(PHP_EOL, $this->paper->getMeta('references')))
            ->filter()
            ->values()
            ->each(fn($reference) => MetaTag::add('citation_reference', $reference));

        MetaTag::add('og:title', e($this->paper->getMeta('title')));
        MetaTag::add('og:type', 'paper');
        MetaTag::add('og:url', route(static::getRouteName(), ['submission' => $this->paper->getKey()]));
        if ($this->paper->getFirstMedia('cover')) {
            MetaTag::add('og:image', $this->paper->getFirstMedia('cover')->getAvailableUrl(['thumb']));
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
