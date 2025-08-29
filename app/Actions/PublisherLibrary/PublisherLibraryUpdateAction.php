<?php

namespace App\Actions\PublisherLibrary;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class PublisherLibraryUpdateAction
{
    use AsAction;

    public function handle(Media $record, array $data): Media
    {
        try {
            DB::beginTransaction();

            $currentScheduledConference = app()->getCurrentScheduledConference();

            if (Storage::disk('local')->exists(data_get($data, 'file_name'))) {
                $media = $currentScheduledConference->addMediaFromDisk($data['file_name'], 'local')
                    ->withCustomProperties(data_get($data, 'custom', []))
                    ->toMediaCollection('publisher-library', 'private-files');

                $media->uuid = $record->uuid;
                $media->order_column = $record->order_column;
                $media->created_at = $record->created_at;
                $media->save();

                $record->delete();
                $record = $media;
                
            } else {
          
                $record->setCustomProperty('is_public', data_get($data, 'custom.is_public', false));
                $record->save();
            }

            
            if (data_get($data, 'meta')) {
                $record->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $record;
    }
}
