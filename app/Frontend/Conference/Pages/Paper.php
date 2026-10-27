<?php

namespace App\Frontend\Conference\Pages;

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
            ->with(['proceeding', 'track.translate', 'media', 'meta', 'translate', 'galleys.file.media', 'authors' => fn ($query) => $query->with(['role.translate', 'meta'])])
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
        return (string) $this->paper->translated('title');
    }

    public function addHead()
    {
        Hook::add('Frontend::Views::Head', function ($hookName, &$output) {
            if ($licenseUrl = $this->paper->getMeta('license_url')) {
                $output .= '<link rel="license" href="'.$licenseUrl.'">';
            }
        });
    }

    public function addMetadata(): void
    {
        $conference = $this->paper->conference;

        MetaTag::add(property: 'og:site_name', content: e($conference->translated('name')));
        MetaTag::add(property: 'og:description', content: e($conference->translated('description')));
        MetaTag::add(property: 'og:title', content: $this->paper->translated('title'));
        MetaTag::add(property: 'og:type', content: 'paper');
        MetaTag::add(property: 'og:url', content: route(static::getRouteName(), ['submission' => $this->paper->getKey()]));
        if ($this->paper->getFirstMedia('cover')) {
            MetaTag::add(property: 'og:image', content: $this->paper->getFirstMedia('cover')->getAvailableUrl(['thumb']));
        }

        $this->addBibliographicMetadata();

        Hook::call('Frontend::Paper::addMetadata', [$this, $this->paper]);
    }

    /**
     * Google Scholar and Dublin Core tags. The paper language is the primary metadata;
     * Dublin Core also lists the translations into other languages.
     */
    protected function addBibliographicMetadata(): void
    {
        $paper = $this->paper;
        $primary = $paper->getPrimaryContentLocale();
        $languageTag = $paper->getLanguageTag();
        $title = $paper->translated('title', $primary);
        $abstract = static::plainText($paper->translated('abstract', $primary));

        MetaTag::add(name: 'citation_title', content: $title);
        MetaTag::add(name: 'citation_language', content: $languageTag);

        if (filled($abstract)) {
            MetaTag::add(name: 'citation_abstract', content: $abstract);
        }

        foreach ($paper->translated('keywords', $primary) as $keyword) {
            MetaTag::add(name: 'citation_keywords', content: $keyword);
        }

        MetaTag::add(name: 'DC.Title', content: $title, xmlLang: $languageTag);

        foreach ($paper->getOtherTranslations('title') as $locale => $translation) {
            MetaTag::add(name: 'DC.Title.Alternative', content: $translation, xmlLang: $paper->getLanguageTag($locale));
        }

        if (filled($abstract)) {
            MetaTag::add(name: 'DC.Description', content: $abstract, xmlLang: $languageTag);
        }

        foreach ($paper->getOtherTranslations('abstract') as $locale => $translation) {
            MetaTag::add(name: 'DC.Description', content: static::plainText($translation), xmlLang: $paper->getLanguageTag($locale));
        }

        MetaTag::add(name: 'DC.Language', content: $languageTag);
    }

    protected static function plainText(?string $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
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
            route(Home::getRouteName()) => __('general.home'),
            route(Proceedings::getRouteName()) => __('general.proceedings'),
            route(ProceedingDetail::getRouteName(), [$this->paper->proceeding->id]) => Str::limit(
                $this->paper->proceeding->seriesTitle(),
                70
            ),
            __('general.paper'),
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
