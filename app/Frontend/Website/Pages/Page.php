<?php

namespace App\Frontend\Website\Pages;

use App\Facades\Hook;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Rahmanramsi\LivewirePageGroup\Pages\Page as BasePage;

abstract class Page extends BasePage
{
    public function render(): View
    {
        $view = null;
        $data = $this->getViewData();
        $layout = static::getLayout();
        $layoutData = $this->getLayoutData();

        $class = Str::replace('\\', '::', static::class);

        if (Hook::call("$class::render", [$this, &$data, &$layout, &$view])) {
            return $view;
        }

        return view(static::$view, $data)
            ->layout($layout, [
                'livewire' => $this,
                ...$layoutData,
            ])
            ->title($this->getTitle());
    }
}
