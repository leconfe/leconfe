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

    public function addMetadata(): void
    {
        $site = app()->getSite();
        $conference = $this->paper->conference;

        MetaTag::add('gs_meta_revision', '1.1');
        MetaTag::add('citation_title', e($this->paper->getMeta('title')));
        MetaTag::add('citation_abstract', strip_tags($this->paper->getMeta('abstract')));

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

        if ($site->getMeta('publisher_name')) {
            MetaTag::add('citation_publisher', e($site->getMeta('publisher_name')));
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

        $this->addEprintMetadata();

        Hook::call('Frontend::Paper::addMetadata', [$this, $this->paper]);
    }

    public function addEprintMetadata()
    {
        $site = app()->getSite();

        MetaTag::add('eprints.eprintid', $this->paper->id);
        MetaTag::add('eprints.metadata_visibility', 'show');

        $this->paper->authors->each(function ($author) {
            MetaTag::add('eprints.creators_name', $author->fullName);
        });

        MetaTag::add('eprints.title', e($this->paper->getMeta('title')));
        MetaTag::add('eprints.ispublished', "pub");
        MetaTag::add('eprints.full_text_status', "public");
        MetaTag::add('eprints.type', "article");
        MetaTag::add('eprints.abstract', strip_tags($this->paper->getMeta('abstract')));
        MetaTag::add('eprints.date', $this->paper->published_at?->format('Y/m/d'));
        MetaTag::add('eprints.date_type', "published");
        MetaTag::add('eprints.full_text_status', "public");
        MetaTag::add('eprints.document_url', route(static::getRouteName(), ['submission' => $this->paper->getKey()]));
        MetaTag::add('eprints.publication', $this->paper->conference->name);
        MetaTag::add('eprints.publisher', strip_tags($site->getMeta('publisher_name')));
        MetaTag::add('eprints.institution', strip_tags($site->getMeta('publisher_name')));
        MetaTag::add('eprints.citation', trim(preg_replace('/\s+/', ' ', strip_tags(Citation::getCitation($this->paper, 'apa')))));
        MetaTag::add('eprints.refereed', "TRUE");
        MetaTag::add('eprints.volume', e($this->paper->proceeding->volume));
        MetaTag::add('eprints.number', e($this->paper->proceeding->number));
        MetaTag::add('eprints.pagerange', $this->paper->getMeta('article_pages'));
        MetaTag::add('eprints.keywords', implode(', ', $this->paper->getMeta('keywords')));
        MetaTag::add('eprints.issn', $this->paper->conference->getMeta('issn'));
        $this->paper->galleys->each(function ($galley) {
            if ($galley->isPdf()) {
                MetaTag::add('eprints.document_url', $galley->getUrl());
            }
        });
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
