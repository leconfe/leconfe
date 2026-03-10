<?php

namespace App\Frontend\Website\Pages;

use App\Http\Middleware\RedirectToConference;
use App\Models\Meta;
use App\Models\ScheduledConference;
use App\Models\Scopes\ConferenceScope;
use App\Models\Site;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    use WithoutUrlPagination, WithPagination;

    protected static string $view = 'frontend.website.pages.home';

    public $filter = [
        'faculty' => [
            'search' => '',
            'value' => []
        ],
        'category' => [
            'search' => '',
            'value' => []
        ],
        'conference' => [
            'search' => '',
            'value' => []
        ],
        'search' => [
            'value' => ''
        ]
    ];

    // Lazy-load flags
    public bool $loadCategories = false;
    public bool $loadFaculties = false;
    public array $categoriesCache = [];

    protected static string|array $routeMiddleware = [
        RedirectToConference::class
    ];

    public function getTitle(): string|Htmlable
    {
        return __('general.home');
    }

    public function getEloquentQuery()
    {
        return ScheduledConference::query()
            ->withoutGlobalScopes([
                ConferenceScope::class,
            ]);
    }

    public function resetFilter(string $type = null): void
    {
        if ($type) {
            $this->filter[$type]['search'] = '';
            $this->filter[$type]['value'] = [];
        } else {
            $this->reset(['filter']);
        }
    }

    protected function getViewData(): array
    {
        // categories (lazy-loaded, cached after first load)
        $categories = empty($this->categoriesCache) ? collect() : collect($this->categoriesCache);
        if ($this->loadCategories && !empty($this->filter['category']['search'])) {
            $search = $this->filter['category']['search'];
            $categories = $categories->filter(function ($value) use ($search) {
                return stripos($value, $search) !== false;
            })->values();
        }

        // faculties (lazy-loaded)
        $faculties = collect();
        if ($this->loadFaculties) {
            $facultiesQuery = Meta::whereNot('type', 'null')->where('key', 'faculty');
            if (!empty($this->filter['faculty']['search'])) {
                $facultiesQuery->whereRaw('LOWER(value) LIKE ?', ['%' . mb_strtolower($this->filter['faculty']['search']) . '%']);
            }
            $faculties = $facultiesQuery->distinct()->orderBy('value')->get()->pluck('value')->unique()->values();
        }

        $featuredScheduledConferences = $this->getEloquentQuery()
            ->with([
                'conference',
                'media',
                'meta',
            ])
            ->whereNotNull('featured')
            ->orderBy('featured', 'ASC')
            ->get();

        $scheduledQuery = $this->getEloquentQuery()
            ->with([
                'conference',
                'media',
                'meta',
            ])
            ->published()
            ->orderBy('date_start', 'DESC');

        // Apply Livewire checkbox filters if provided
        if (!empty($this->filter['category']['value'])) {
            $scheduledQuery->filterByCategories($this->filter['category']['value']);
        }

        if (!empty($this->filter['faculty']['value'])) {
            $scheduledQuery->whereHas('meta', function ($m) {
                $m->where('key', 'faculty')
                    ->whereIn('value', $this->filter['faculty']['value']);
            });
        }

        if ($this->filter['search']['value'] !== '') {
            $scheduledQuery->where(function ($q) {
                $searchTerm = '%' . mb_strtolower($this->filter['search']['value']) . '%';
                $q->whereRaw('LOWER(title) LIKE ?', [$searchTerm]);
            });
        }

        $scheduledConferences = $scheduledQuery->get();

        return [
            'scheduledConferences' => $scheduledConferences,
            'featuredScheduledConferences' => $featuredScheduledConferences,
            'faculties' => $faculties,
            'categories' => $categories,
        ];
    }

    /**
     * Ensure categories are loaded when the dropdown is opened.
     */
    public function changeStateLoadCategories(bool $load): void
    {
        // Populate cache on first open to avoid repeated DB/meta calls during updates
        if ($load && empty($this->categoriesCache)) {
            $this->categoriesCache = Site::getSite()->getMeta('scheduled_conference_categories', []);
        }
        $this->loadCategories = $load;
    }

    /**
     * Ensure faculties are loaded when the dropdown is opened.
     */
    public function changeStateLoadFaculties(bool $load): void
    {
        $this->loadFaculties = $load;
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
