<?php

namespace App\Managers;

use App\Classes\Sidebar;
use Illuminate\Support\Collection;

class SidebarManager
{
    protected array $sidebars = [];

    public function boot(): void
    {
        if (! $this->sidebars) {
            return;
        }

        foreach ($this->sidebars as $sidebar) {
            if (! $sidebar instanceof Sidebar) {
                throw new \Exception("{$sidebar->getName()} must be an instance of ".Sidebar::class);
            }
        }
    }

    public function register(Sidebar|array $sidebars): void
    {
        if ($sidebars instanceof Sidebar) {
            $sidebars = [$sidebars];
        }

        // Validate sidebar inside the array
        foreach ($sidebars as $sidebar) {
            if (! $sidebar instanceof Sidebar) {
                throw new \Exception("Error: could not register sidebar. {$sidebar->getName()} must be an instance of ".Sidebar::class);
            }
        }

        $this->sidebars = array_merge($this->sidebars, $sidebars);
    }

    public function get(bool $activeOnly = true): Collection
    {
        return collect($this->sidebars)
            ->reject(function (Sidebar $sidebar) use ($activeOnly) {
                if ($activeOnly) {
                    return ! $this->isActiveSidebar($sidebar);
                }

                return false;
            })
            ->sortBy(function (Sidebar $sidebar) {
                // Sort by the order of the block in the active block list if it exists.
                // start from 1 to avoid 0 index
                // if the block is not in the active block list, it will be placed at the end
                $index = array_search($sidebar->getId(), $this->getActiveList());

                return ($index === false) ? count($this->getActiveList()) + 1 : $index + 1;
            })
            ->values();
    }

    public function isActiveSidebar(Sidebar $sidebar): bool
    {
        return in_array($sidebar->getId(), $this->getActiveList());
    }

    public function getActiveList(): array
    {
        $context = app()->getCurrentScheduledConference() ?? app()->getCurrentScheduledConference() ?? app()->getSite();

        return $context->getMeta('sidebars') ?? [];
    }

    public function updateActiveList(array $sidebars): void
    {
        $context = app()->getCurrentScheduledConference() ?? app()->getCurrentScheduledConference() ?? app()->getSite();
        $context->setMeta('sidebars', $sidebars);
    }
}
