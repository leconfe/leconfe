<?php

namespace App\Frontend\Website\Pages;

use App\Models\Proceeding;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\WithoutUrlPagination;
use Livewire\WithPagination;

class Proceedings extends Page
{
    use WithoutUrlPagination, WithPagination;

    protected static string $view = 'frontend.website.pages.proceedings';

    public function getTitle(): string|Htmlable
    {
        return __('general.proceedings');
    }

    public function getBreadcrumbs(): array
    {
        return [
            route(Home::getRouteName()) => 'Home',
            $this->getTitle(),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'proceedings' => Proceeding::query()
                ->withoutGlobalScopes()
                ->published()
                ->ordered()
                ->get()
        ];
    }
}
