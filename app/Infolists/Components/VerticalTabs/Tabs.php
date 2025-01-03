<?php

namespace App\Infolists\Components\VerticalTabs;

use Closure;
use App\Facades\Hook;
use Illuminate\Support\Str;
use Filament\Infolists\Components\Tabs as ComponentsTabs;

class Tabs extends ComponentsTabs
{
    protected string $view = 'infolists.components.vertical-tabs.tabs';

    protected bool|Closure $sticky = false;

    protected string|Closure $position = 'left';

    protected string|Closure $verticalSpace = '';

    public function position(string $position): static
    {
        $position = $this->evaluate($position);
        if ($position === 'left' || $position === 'right') {
            $this->position = $position;
        } else {
            throw new \Exception('Invalid position provided. Only "left" and "right" are allowed.');
        }

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
    }

    public function verticalSpace(string $verticalSpace): static
    {
        $verticalSpace = $this->evaluate($verticalSpace);
        if (str_starts_with($verticalSpace, 'space-y-')) {
            $this->verticalSpace = $verticalSpace;
        } else {
            throw new \Exception('Invalid verticalSpace provided. Only "space-y-" are allowed.');
        }

        return $this;
    }

    public function getVerticalSpace(): string
    {
        return $this->verticalSpace;
    }

    public function sticky(bool|Closure $sticky = true): static
    {
        $this->sticky = $this->evaluate($sticky);

        return $this;
    }

    public function isSticky(): bool
    {
        return $this->sticky;
    }

    public function childComponents(array | Closure $components): static
    {
        $id = Str::slug($this->label);

        Hook::call('VerticalTabs::Tabs::childComponents', [$id, &$components, $this]);

        $this->childComponents = $components;

        return $this;
    }
}
