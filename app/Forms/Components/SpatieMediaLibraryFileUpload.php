<?php

namespace App\Forms\Components;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload as FileUpload;
use Illuminate\Database\Eloquent\Model;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use function Livewire\invade;

class SpatieMediaLibraryFileUpload extends FileUpload
{
	protected function setUp(): void
	{
		parent::setUp();

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
				->usingName($component->getMediaName($file) ?? static::getClientOriginalName($file))
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

		if ($contents = $inv->storage->get($inv->path.'.json')) {
			$metaFileData = json_decode($contents, true);
		}
		return $metaFileData;
	}
}
