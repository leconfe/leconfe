<?php

namespace App\Infolists\Components\VerticalTabs;

use App\Facades\Hook;
use Closure;
use Filament\Infolists\Components\Tabs\Tab as ComponentsTab;

class Tab extends ComponentsTab
{
    protected string $view = 'infolists.components.vertical-tabs.tab';

    public function childComponents(array | Closure $components): static
    {
        $id = $this->id;

        Hook::call('VerticalTabs::Tab::childComponents', [$id, &$components, $this]);

        $this->childComponents = $components;

        return $this;
    }
}
