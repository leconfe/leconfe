<?php

namespace App\Panel\Conference\Livewire;

use App\Models\Media;
use Livewire\Component;
use Filament\Forms\Form;
use App\Models\Proceeding;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Classes\DOIGenerator;
use App\Models\Enums\DOIStatus;
use App\Models\ScheduledConference;
use App\Tables\Columns\IndexColumn;
use Filament\Tables\Actions\Action;
use Filament\Support\Enums\MaxWidth;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Checkbox;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToCheckFileExistence;

class PublisherLibrary extends Component implements HasForms, HasTable
{
	use InteractsWithForms;
	use InteractsWithTable;

	public function table(Table $table): Table
	{
		return $table
			->query(
				Media::query()
					->where('model_type', ScheduledConference::class)
					->where('collection_name', 'publisher-library'),
			)
			->defaultSort('order_column', 'asc')
			->reorderable('order_column')
			->columns([
				IndexColumn::make('no'),
				TextColumn::make('name')
					->searchable(),
				IconColumn::make('public_access')
					->getStateUsing(fn(Media $record) => $record->getCustomProperty('is_public'))
					->boolean()
					->trueIcon('heroicon-s-check')
					->falseIcon('heroicon-o-x-mark'),

			])
			->headerActions([
				Action::make('add_a_file')
					->label(__('general.add_a_file'))
					->modalWidth(MaxWidth::ExtraLarge)
					->action(function (array $data) {
						$currentScheduledConference = app()->getCurrentScheduledConference();
						$currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
							->usingName($data['name'])
							->withCustomProperties($data['custom'])
							->toMediaCollection('publisher-library', 'private-files');
					})
					->form(fn($form) => $this->form($form)),
			])
			->actions([
				EditAction::make()
					->fillForm(function (Media $record, array $data): array {
						$data['name'] = $record->name;
						$data['file_name'] = [$record->file_name];
						$data['custom']['is_public'] = $record->getCustomProperty('is_public');
						return $data;
					})
					->modalWidth(MaxWidth::ExtraLarge)
					->form(fn($form, $record) => $this->form($form))
					->using(function (Media $record, $data) {
						$currentScheduledConference = app()->getCurrentScheduledConference();
						
						if(Storage::disk('local')->exists($data['file_name'])){
							$media = $currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
								->usingName($data['name'])
								->withCustomProperties($data['custom'])
								->toMediaCollection('publisher-library', 'private-files');
						}

						$record->delete();

						$media->uuid = $record->uuid;
						$media->order_column = $record->order_column;
						$media->created_at = $record->created_at;
						$media->save();
					}),
				DeleteAction::make()
			]);
	}

	public function form(Form $form)
	{
		return $form
			->schema([
				TextInput::make('name')
					->required(),
				FileUpload::make('file_name')
					->disk('local')
					->preserveFilenames()
					->afterStateHydrated(static function (BaseFileUpload $component, Media | null $record): void {
						if (blank($record)) {
							$component->state([]);

							return;
						}

						$component->state([((string) Str::uuid()) => $record->file_name]);
					})
					->getUploadedFileUsing(static function (BaseFileUpload $component, Media | null $record): ?array {
						if(blank($record)) {
							return null;
						}
						
						$url = null;

						try {
							$url = $record?->getTemporaryUrl(
								now()->addMinutes(5),
							);
						} catch (\Throwable $exception) {
							// This driver does not support creating temporary URLs.
						}

						$url ??= $record?->getUrl();

						return [
							'name' => $record?->getAttributeValue('file_name'),
							'size' => $record?->getAttributeValue('size'),
							'type' => $record?->getAttributeValue('mime_type'),
							'url' => $url,
						];
					})
					->required(),
				Checkbox::make('custom.is_public')
					->label(__('general.allow_public_access')),
			]);
	}

	public function render()
	{
		return view('panel.conference.livewire.table');
	}
}
