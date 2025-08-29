<?php

namespace App\Actions\PublisherLibrary;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PublisherLibraryCreateAction
{
    use AsAction;

    public function handle(array $data): Media
    {
        try {
            DB::beginTransaction();

            $currentScheduledConference = app()->getCurrentScheduledConference();

            $media = $currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
                ->withCustomProperties(data_get($data, 'custom', []))
                ->toMediaCollection('publisher-library', 'private-files');

            if (data_get($data, 'meta')) {
                $media->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $media;
    }
}
