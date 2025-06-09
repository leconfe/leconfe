<?php

namespace App\Frontend\Website\Pages;

use App\Models\Conference;
use App\Models\Enums\ScheduledConferenceState;
use App\Models\Meta;
use App\Models\ScheduledConference;
use App\Models\StaticPage;
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
        return [
            'homepage' => StaticPage::getHome(),
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
