<?php

namespace App\Actions\Timeline;

use App\Models\Timeline;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TimelineUpdateAction
{
    use AsAction;

    public function handle(Timeline $timeline, array $data): Timeline
    {
        try {
            DB::beginTransaction();

            $timeline->update($data);

            if (data_get($data, 'meta')) {
                $timeline->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $timeline;
    }
}