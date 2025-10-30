<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\FileUploadController as BaseController;

class FileUploadController extends BaseController
{
	public function validateAndStore($files, $disk)
    {
        Validator::make(['files' => $files], [
            'files.*' => FileUploadConfiguration::rules()
        ])->validate();

        $fileHashPaths = collect($files)->map(function ($file) use ($disk) {
            return static::storeTemporaryFile($file, $disk);
        });

        // Strip out the temporary upload directory from the paths.
        return $fileHashPaths->map(function ($path) { return str_replace(FileUploadConfiguration::path('/'), '', $path); });
    }

	public static function storeTemporaryFile($file, $disk)
    {
        $filename = static::generateHashName($file);
        $metaFilename = $filename . '.json';
        
        Storage::disk($disk)->put('/'. FileUploadConfiguration::path($metaFilename), json_encode([
            'name' => $file->getClientOriginalName(),
            'type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'hash' => $file->hashName(),
        ]));

        return $file->storeAs('/'.FileUploadConfiguration::path(), $filename, [
            'disk' => $disk
        ]);
    }

    public static function generateHashName($file)
    {
        $hash = str()->random(40);
        $extension = '.'.$file->getClientOriginalExtension();

        return $hash.$extension;
    }
}
