<?php

namespace App\Actions\Timeline;

use App\Models\Timeline;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TimelineCreateAction
{
    use AsAction;

    public function handle(array $data): Timeline
    {
        try {
            DB::beginTransaction();

            $timeline = Timeline::create($data);

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