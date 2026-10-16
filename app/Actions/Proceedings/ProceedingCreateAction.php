<?php

namespace App\Actions\Proceedings;

use App\Models\Proceeding;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProceedingCreateAction
{
    use AsAction;

    public function handle(array $data): Proceeding
    {
        try {
            DB::beginTransaction();

            $proceeding = new Proceeding;
            $data = $proceeding->syncTranslatableState($data);
            $proceeding->fill($data)->save();

            if (data_get($data, 'meta')) {
                $proceeding->setManyMeta($data['meta']);
            }

            $proceeding->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $proceeding;
    }
}
