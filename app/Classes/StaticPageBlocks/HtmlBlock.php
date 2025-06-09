<?php

namespace App\Classes\StaticPageBlocks;

use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Builder;
use Illuminate\Contracts\View\View;

class HtmlBlock extends BaseBlock
{
    protected string $view = 'frontend.website.pages.blocks.html';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->label("HTML")
			->schema([
				TinyEditor::make('content')
					->hiddenLabel()
					->profile('advanced')
					->minHeight(500)
			])
			->preview('filament.forms.block-previews.html');
	}
}
