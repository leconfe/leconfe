<?php

namespace App\Actions\PublisherLibrary;

use App\Models\Media;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class PublisherLibraryUpdateAction
{
    use AsAction;

    public function handle(Media $record, array $data): Media
    {
        try {
            DB::beginTransaction();
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
