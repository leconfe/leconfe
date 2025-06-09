<?php

namespace App\Classes\StaticPageBlocks;

use App\Forms\Components\TinyEditor;
use App\Models\ScheduledConference;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class ConferenceListBlock extends BaseBlock
{
	protected string $view = 'frontend.website.pages.blocks.conference-list';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->label('Conference List')
			->schema([
				TextInput::make('title'),
			])
			->label(function (?array $state): string {
				if ($state === null) {
					return 'Conference List';
				}

				return $state['title'] ?: 'Conference List';
			})
			->maxItems(1);
		// ->preview('filament.forms.block-previews.html')
	}

	public function getViewData(): array
	{
		$conferences = ScheduledConference::with('meta')->get();

		$this->viewData['title'] ??= 'Conference List';

		return [
			...compact('conferences'),
			...$this->viewData
		];
	}
}
