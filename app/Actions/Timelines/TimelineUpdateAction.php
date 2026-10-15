<?php

namespace App\Actions\Timelines;

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

            $data = $timeline->syncTranslatableState($data);

            $timeline->update($data);

            $timeline->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $timeline;
    }
}
