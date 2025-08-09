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
            $media = Media::find($data['id']);
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
