<?php

namespace App\Managers;

use App\Classes\StaticPageBlocks\BaseBlock;
use App\Classes\StaticPageBlocks\ConferenceListBlock;
use App\Classes\StaticPageBlocks\GalleryBlock;
use App\Classes\StaticPageBlocks\HtmlBlock;
use App\Classes\StaticPageBlocks\SpeakersBlock;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Actions\Action as ActionForm;
use Filament\Support\Enums\Alignment;

class StaticPageBlockManager
{
	protected array $blocks =  [];

	public function getBlocks(): array
	{
		if (empty($this->blocks)) {
			$blocks = [
				'html' => HtmlBlock::class,
				'speakers' => SpeakersBlock::class,
				'gallery' => GalleryBlock::class,
				'conference-list' => ConferenceListBlock::class,
			];

			// 	TODO : Add Hooks here

			$this->blocks = $blocks;
		}

		return $this->blocks;
	}

	public function initBlock(string $name, array $data): ?BaseBlock
	{
		$blockClass = $this->getBlock($name);
		if (!$blockClass) return null;

		return new $blockClass($data);
	}

	public function getBlock($name): ?string
	{
		return $this->getBlocks()[$name] ?? null;
	}

	public function getBlockBuilders() : array 
	{
		return collect($this->getBlocks())
			->map(fn($blockClass, $key) => $blockClass::getBuilderBlock(Builder\Block::make($key)))
			->toArray();
	}

	public function getBuilder(): Builder
	{
		return Builder::make('contents')
			->collapsible()
			->persistCollapsed()
			->addActionAlignment(Alignment::Start)
			->blockIcons()
			->blockPreviews(areInteractive: false)
			->hiddenLabel()
			->blockNumbers(false)
			->editAction(fn(ActionForm $action) => $action->slideOver())
			->addAction(fn(ActionForm $action) => $action->slideOver())
			->addBetweenAction(fn(ActionForm $action) => $action->slideOver())
			->blocks($this->getBlockBuilders());
	}
}
