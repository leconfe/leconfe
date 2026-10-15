<?php

namespace App\Actions\Timelines;

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

            $timeline = new Timeline;
            $data = $timeline->syncTranslatableState($data);
            $timeline->fill($data)->save();

            $timeline->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $timeline;
    }
}
