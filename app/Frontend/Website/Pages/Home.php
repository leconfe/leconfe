<?php

namespace App\Frontend\Website\Pages;

use App\Http\Middleware\RedirectToConference;
use App\Http\Middleware\RedirectToScheduledConference;
use App\Models\Conference;
use App\Models\Meta;
use App\Models\ScheduledConference;
use App\Models\Scopes\ConferenceScope;
use App\Models\Scopes\ScheduledConferenceScope;
use App\Models\Topic;
use Filament\Forms\Components\Select;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    use WithoutUrlPagination, WithPagination;

    protected static string $view = 'frontend.website.pages.home';

    #[Url(as: 'faculty')]
    public $faculty;
    #[Url(as: 'topic')]
    public $topic;
    #[Url(as: 'conference')]
    public $conference;

    public $filter = [
        'faculty' => [
            'search' => '',
            'value' => []
        ],
        'topic' => [
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
        // $this->reset(['faculty', 'topic', 'conference']);
        if ($type) {
            $this->filter[$type]['search'] = '';
            $this->filter[$type]['value'] = [];
        } else {
            $this->reset(['filter']);
        }
    }

    protected function getViewData(): array
    {
        // topic
        $topicsQuery = Topic::withoutGlobalScopes()->websiteTopics();
        if (!empty($this->filter['topic']['search'])) {
            $topicsQuery->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($this->filter['topic']['search']) . '%']);
        }
        $topics = $topicsQuery->pluck('name', 'id');

        // faculty
        $facultiesCollection = ScheduledConference::withoutGlobalScopes()
            ->get()
            ->map(fn($s) => $s->getMeta('faculty'))
            ->filter();
        if (!empty($this->filter['faculty']['search'])) {
            $facultiesCollection = $facultiesCollection->filter(function ($faculty) {
                return Str::contains(Str::lower($faculty), mb_strtolower($this->filter['faculty']['search']));
            });
        }
        $faculties = $facultiesCollection->unique()->sort()->values();

        // TODO: remove
        $conferencesQuery = Conference::withoutGlobalScopes()
            ->whereHas('scheduledConferences', function ($q) {
                $q->withoutGlobalScopes();
            });
        if (!empty($this->filter['conference']['search'])) {
            $conferencesQuery->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($this->filter['conference']['search']) . '%']);
        }
        $conferences = $conferencesQuery->pluck('name', 'id');


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
        if (!empty($this->filter['topic']['value'])) {
            $scheduledQuery->whereHas('topics', function ($t) {
                $t->withoutGlobalScopes()
                    ->whereIn('name', $this->filter['topic']['value']);
            });
        }

        if (!empty($this->filter['faculty']['value'])) {
            $scheduledQuery->whereHas('meta', function ($m) {
                $m->where('key', 'faculty')
                    ->whereIn('value', $this->filter['faculty']['value']);
            });
        }

        // TODO: remove
        if (!empty($this->filter['conference']['value'])) {
            $scheduledQuery->whereIn('conference_id', $this->filter['conference']['value']);
        }

        $scheduledConferences = $scheduledQuery->get();

        // TODO: remove
        $selectedConferences = [];
        if (!empty($this->filter['conference']['value'])) {
            $selectedConferences = Conference::whereIn('id', $this->filter['conference']['value'])
                ->get()
                ->pluck('name')
                ->toArray();
        }

        return [
            'scheduledConferences' => $scheduledConferences,
            'featuredScheduledConferences' => $featuredScheduledConferences,
            'faculties' => $faculties,
            'topics' => $topics,
            'conferences' => $conferences,
            'selectedConferences' => $selectedConferences, // TODO: remove
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
