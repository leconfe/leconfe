<?php

namespace App\Classes\StaticPageBlocks;

use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\View\View;

class GalleryBlock extends BaseBlock
{
    protected string $view = 'frontend.website.pages.blocks.gallery';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->label("Gallery")
			->schema([
				TextInput::make('title')
					->required()
					->live(),
				FileUpload::make('images')
					->hiddenLabel()
					->downloadable()
					->multiple()
					->panelLayout('grid')
					->reorderable()
					->required(),
			])
			->label(function (?array $state): string {
				if ($state === null) return 'Gallery';

				return $state['title'] ?: 'Gallery';
			})
			->preview('filament.forms.block-previews.gallery')
			;
	}
}
