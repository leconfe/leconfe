<?php

namespace App\Forms\Components;

use Illuminate\Support\Str;
use function Livewire\invade;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\BaseFileUpload;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload as FileUpload;

class SpatieMediaLibraryFileUpload extends FileUpload
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->getUploadedFileNameForStorageUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file) {
			return $component->shouldPreserveFilenames() ?  static::getClientOriginalName($file) : (Str::ulid() . '.' . $file->getClientOriginalExtension());
		});

		$this->saveUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, TemporaryUploadedFile $file, ?Model $record): ?string {
			if (! method_exists($record, 'addMediaFromString')) {
				return $file;
			}

			try {
				if (! $file->exists()) {
					return null;
				}
			} catch (UnableToCheckFileExistence $exception) {
				return null;
			}

			/** @var FileAdder $mediaAdder */
			$mediaAdder = $record->addMediaFromString($file->get());

			$filename = $component->getUploadedFileNameForStorage($file);
			$media = $mediaAdder
				->addCustomHeaders($component->getCustomHeaders())
				->usingFileName($filename)
				->usingName($component->getMediaName($file) ??  pathinfo(static::getClientOriginalName($file), PATHINFO_FILENAME))
				->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
				->withCustomProperties($component->getCustomProperties())
				->withManipulations($component->getManipulations())
				->withResponsiveImagesIf($component->hasResponsiveImages())
				->withProperties($component->getProperties())
				->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

			return $media->getAttributeValue('uuid');
		});
	}

	static function getClientOriginalName(TemporaryUploadedFile $file)
	{
		return static::getMetaFileData($file)['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
	}

	static function getMetaFileData(TemporaryUploadedFile $file)
	{
		$metaFileData = [];

		$inv = invade($file);

		if ($contents = $inv->storage->get($inv->path . '.json')) {
			$metaFileData = json_decode($contents, true);
		}
		return $metaFileData;
	}
}
