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

class SpeakersBlock extends BaseBlock
{
	protected string $view = 'frontend.website.pages.blocks.speakers';

	public static function getBuilderBlock(Builder\Block $block): Builder\Block
	{
		return $block
			->schema([
				TextInput::make('title')
					->live(onBlur: true)
					->required(),
				TinyEditor::make('description')
					->profile('advanced'),
				Repeater::make('speakers')
					->collapsible()
					->reorderableWithButtons()
					->reorderableWithDragAndDrop(false)
					->addActionLabel('Add Speaker')
					->addActionAlignment(Alignment::Start)
					->itemLabel(fn(array $state): ?string => $state['name'] ?? null)
					->schema([
						Grid::make(5)
							->schema([
								FileUpload::make('profile_picture')
									->required()
									->avatar(),
								Grid::make(1)
									->columnSpan(4)
									->schema([
										TextInput::make('name')
											->required(),
										TextInput::make('affiliation'),
									]),
							]),
						Textarea::make('biography'),
						Fieldset::make(__('general.scholar_profile'))
							->schema([
								Grid::make(2)
									->schema([
										TextInput::make('orcid_url')
											->prefixIcon('academicon-orcid')
											->url()
											->label(__('general.orcid_id')),
										TextInput::make('google_scholar_url')
											->prefixIcon('academicon-google-scholar')
											->url()
											->label(__('general.google_scholar')),
										TextInput::make('scopus_url')
											->label(__('general.scopus_id'))
											->url()
											->prefixIcon('academicon-scopus-square'),
									]),
							]),
					]),
			])
			->preview('filament.forms.block-previews.speaker-block')
			->label(function (?array $state): string {
				if ($state === null) return 'Speakers';

				return $state['title'] ?: 'Speakers';
			});
	}
}
