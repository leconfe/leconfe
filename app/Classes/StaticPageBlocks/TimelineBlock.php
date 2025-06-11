<?php

namespace App\Classes\StaticPageBlocks;

use App\Forms\Components\TinyEditor;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Alignment;

class TimelineBlock extends BaseBlock
{
	protected string $view = 'frontend.website.pages.blocks.timeline';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->schema([
				TextInput::make('title')
					->live(onBlur: true)
					->required(),
				RichEditor::make('description'),
				Repeater::make('timelines')
					->collapsible()
					->reorderableWithButtons()
					->reorderableWithDragAndDrop(false)
					->addActionAlignment(Alignment::Start)
					->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
					->schema([
						TextInput::make('name')
							->label(__('general.name'))
							->required(),
						Textarea::make('description')
							->label(__('general.description'))
							->autosize(),
						Grid::make()
							->schema([
								DatePicker::make('date_start')
									->label(__('general.start_date'))
									->required(),
								DatePicker::make('date_end')
									->label(__('general.end_date'))
									->after('date'),
							]),
					]),
			])
			->preview('filament.forms.block-previews.timeline')
			->label(function (?array $state): string {
				return $state['title'] ?? 'Timeline';
			});
	}
}
