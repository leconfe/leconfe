<?php

namespace App\Forms\Components;

use App\Support\Media\RasterImageNormalizer;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload as FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckFileExistence;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

use function Livewire\invade;

class SpatieMediaLibraryFileUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getUploadedFileNameForStorageUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file) {
            return $component->shouldPreserveFilenames() ? static::getClientOriginalName($file) : (Str::ulid().'.'.$file->getClientOriginalExtension());
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

            $contents = static::normalizeImageContents($file);

            // Only decodable raster image data is stored.
            if ($contents === null) {
                return null;
            }

            /** @var FileAdder $mediaAdder */
            $mediaAdder = $record->addMediaFromString($contents);

            $filename = $component->getUploadedFileNameForStorage($file);
            $media = $mediaAdder
                ->addCustomHeaders($component->getCustomHeaders())
                ->usingFileName($filename)
                ->usingName($component->getMediaName($file) ?? pathinfo(static::getClientOriginalName($file), PATHINFO_FILENAME))
                ->storingConversionsOnDisk($component->getConversionsDisk() ?? '')
                ->withCustomProperties($component->getCustomProperties())
                ->withManipulations($component->getManipulations())
                ->withResponsiveImagesIf($component->hasResponsiveImages())
                ->withProperties($component->getProperties())
                ->toMediaCollection($component->getCollection() ?? 'default', $component->getDiskName());

            return $media->getAttributeValue('uuid');
        });
    }

    /**
     * Restrict image fields to raster formats and re-encode raster images so that
     * only decoded pixel data is stored. Non-image files (documents, archives)
     * pass through unchanged.
     */
    protected static function normalizeImageContents(TemporaryUploadedFile $file): ?string
    {
        $mimeType = (string) $file->getMimeType();

        if (RasterImageNormalizer::isRasterImage($mimeType)) {
            return RasterImageNormalizer::normalize($mimeType, $file->get());
        }

        if (RasterImageNormalizer::isImage($mimeType)) {
            return null;
        }

        return $file->get();
    }

    public function image(): static
    {
        return $this->acceptedFileTypes(RasterImageNormalizer::allowedMimeTypes());
    }

    public static function getClientOriginalName(TemporaryUploadedFile $file)
    {
        return static::getMetaFileData($file)['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
    }

    public static function getMetaFileData(TemporaryUploadedFile $file)
    {
        $metaFileData = [];

        $inv = invade($file);

        if ($contents = $inv->storage->get($inv->path.'.json')) {
            $metaFileData = json_decode($contents, true);
        }

        return $metaFileData;
    }
}
