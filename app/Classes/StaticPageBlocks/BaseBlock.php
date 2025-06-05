<?php

namespace App\Classes\StaticPageBlocks;

use Exception;
use Filament\Forms\Components\Builder;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;

abstract class BaseBlock implements Htmlable
{
    protected string $view;

	protected array $viewData;

	public function __construct(array $data)
	{
		$this->viewData = $data;
	}

	public function make(array $data)
	{
		return new static($data);
	}

	abstract public static function getBuilderBlock(Builder\Block $block): Builder\Block;

    public function getViewData(): array
    {
        return $this->viewData;
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }

    public function render(): View
    {
        return view(
            $this->getView(),
            $this->getViewData(),
        );
    }

	/**
     * @return view-string
     */
    public function getView(): string
    {
        if (isset($this->view)) {
            return $this->view;
        }

        throw new Exception('Class [' . static::class . '] extends [' . BaseBlock::class . '] but does not have a [$view] property defined.');
    }
}
