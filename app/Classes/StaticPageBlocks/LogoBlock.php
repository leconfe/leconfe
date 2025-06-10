<?php

namespace App\Classes\StaticPageBlocks;

use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\View\View;

class LogoBlock extends BaseBlock
{
	protected string $view = 'frontend.website.pages.blocks.logo';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->schema([
				TextInput::make('title')
					->live(onBlur: true)
					->required(),
				TinyEditor::make('description')
					->profile('advanced'),
				Repeater::make('logos')
					->collapsible()
					->reorderableWithButtons()
					->reorderableWithDragAndDrop(false)
					->addActionLabel('Add Logo')
					->addActionAlignment(Alignment::Start)
					->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
					->schema([
						FileUpload::make('image')
							->required()
							->image(),
						TextInput::make('name')
							->required(),
						TextInput::make('url')
							->url(),
					]),
			])
			->preview('filament.forms.block-previews.logo')
			->label(function (?array $state): string {
				if ($state === null) return 'Logo';

				return $state['title'] ?: 'Logo';
			});
	}
}
