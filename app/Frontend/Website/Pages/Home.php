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

    public function resetFilters()
    {
        $this->reset(['faculty', 'topic', 'conference']);
    }

    protected function getViewData(): array
    {
        $topics = Topic::withoutGlobalScopes()
            ->whereHas('scheduledConferences', function ($q) {
                $q->withoutGlobalScopes();
            })
            ->pluck('name', 'id');

        $faculties = ScheduledConference::withoutGlobalScopes()
            ->get()
            ->map(fn($s) => $s->getMeta('faculty'))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $conferences = Conference::withoutGlobalScopes()
            ->whereHas('scheduledConferences', function ($q) {
                $q->withoutGlobalScopes();
            })
            ->pluck('name', 'id');

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

        if ($this->topic) {
            $scheduledQuery->whereHas('topics', function ($t) {
                $t->withoutGlobalScopes()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->topic)]);
            });
        }

        if ($this->faculty) {
            $scheduledQuery->whereHas('meta', function ($m) {
                $m->where('key', 'faculty')
                    ->where('value', $this->faculty);
            });
        }

        if ($this->conference) {
            $scheduledQuery->whereHas('conference', function ($c) {
                $c->whereRaw('LOWER(name) = ?', [mb_strtolower($this->conference)]);
            });
        }

        $scheduledConferences = $scheduledQuery->get();

        return [
            'scheduledConferences' => $scheduledConferences,
            'featuredScheduledConferences' => $featuredScheduledConferences,
            'faculties' => $faculties,
            'topics' => $topics,
            'conferences' => $conferences,
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
