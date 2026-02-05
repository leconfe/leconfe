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

            $proceeding = Proceeding::create($data);

            if (data_get($data, 'meta')) {
                $proceeding->setManyMeta($data['meta']);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $proceeding;
    }
}
