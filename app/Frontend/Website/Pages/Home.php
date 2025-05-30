<?php

namespace App\Frontend\Website\Pages;

use App\Models\Conference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Meta;
use App\Models\ScheduledConference;
use App\Models\Topic;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;
use Rahmanramsi\LivewirePageGroup\PageGroup;

class Home extends Page
{
    use WithoutUrlPagination, WithPagination;

    protected static string $view = 'frontend.website.pages.home';

    public const STATE_CURRENT = 'current';

    public const STATE_INCOMING = 'incoming';

    public const STATE_ARCHIVED = 'archived';

    public array $filter = [
        'search' => [
            'value' => '',
        ],
        'scope' => [
            'value' => '',
        ],
        'state' => [
            'value' => [],
        ],
        'topic' => [
            'search' => '',
            'value' => [],
        ],
        'coordinator' => [
            'search' => '',
            'value' => [],
        ],
    ];

    public function getTitle(): string|Htmlable
    {
        return __('general.home');
    }

    public function resetFilter(string $filterName): void
    {
        if (is_string($this->filter[$filterName]['value'])) {

            $this->filter[$filterName]['value'] = '';

        } elseif (is_array($this->filter[$filterName]['value'])) {

            $this->filter[$filterName]['value'] = [];

        }
    }

    public function resetFilters(): void
    {
        $this->filter['scope']['value'] = '';
        $this->filter['state']['value'] = [];
        $this->filter['topic']['value'] = [];
        $this->filter['coordinator']['value'] = [];
    }

    protected function getViewData(): array
    {
        $conferences = Conference::query()
            ->with([
                'media',
                'meta',
                'topics',
                'scheduledConferences',
                'currentScheduledConference' => fn (Builder $query) => $query->with('conference')->withoutGlobalScopes(),
            ]);

        $topicList = Topic::withoutGlobalScopes()
            ->with(['conference'])
            ->select('name')
            ->where('name', 'LIKE', "%{$this->filter['topic']['search']}%")
            ->orderBy('name', 'ASC')
            ->limit(20)
            ->distinct()
            ->get();

        $coordinatorList = Meta::query()
            ->where('metable_type', ScheduledConference::class)
            ->where('key', 'coordinator')
            ->whereRaw('value != ""')
            ->whereNotNull('value')
            ->tobase()
            ->get();

        // data filter

        if (($search = $this->filter['search']['value']) > 0) {
            $conferences->where('name', 'LIKE', "%{$search}%");
        }

        if ($scope = $this->filter['scope']['value']) {
            $conferences
                ->whereHas('meta', function ($query) use ($scope) {
                    $query
                        ->where('key', 'scope')
                        ->where('value', $scope);
                });
        }

        if ($states = $this->filter['state']['value']) {
            $stateOption = Arr::map($states, function ($value) {
                return match (Str::lower($value)) {
                    self::STATE_CURRENT => ScheduledConferenceState::Current,
                    self::STATE_INCOMING => ScheduledConferenceState::Published,
                    self::STATE_ARCHIVED => ScheduledConferenceState::Archived,
                };
            });

            $conferences
                ->whereHas('scheduledConferences', function ($query) use ($stateOption) {
                    $query
                        ->withTrashed()
                        ->withoutGlobalScopes()
                        ->whereIn('state', $stateOption);
                });

        }

        if (! empty($topics = $this->filter['topic']['value'])) {
            $conferences
                ->whereHas('topics', function ($query) use ($topics) {
                    $query
                        ->withoutGlobalScopes()
                        ->whereIn('name', $topics);
                });
        }

        if (! empty($coordinators = $this->filter['coordinator']['value'])) {
            $conferences
                ->whereHas('scheduledConferences', function ($query) use ($coordinators) {
                    $query
                        ->withTrashed()
                        ->withoutGlobalScopes()
                        ->whereHas('meta', function ($query) use ($coordinators) {
                            $query
                                ->where('key', 'coordinator')
                                ->whereIn('value', $coordinators);
                        });
                });
        }

        return [
            'topics' => $topicList,
            // 'conferences' => $conferences->get(),
            'coordinatorList' => $coordinatorList,
            // Selected Filter Data
            'scopeSelected' => $this->filter['scope']['value'],
            'stateSelected' => $this->filter['state']['value'],
            'topicSelected' => $this->filter['topic']['value'],
            'coordinatorSelected' => $this->filter['coordinator']['value'],
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
