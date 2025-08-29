<?php

namespace App\Actions\Proceedings;

use App\Models\Proceeding;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProceedingUpdateAction
{
    use AsAction;

    public function handle(Proceeding $proceedingData, array $data): Proceeding
    {
        try {
            DB::beginTransaction();

            $proceedingData->update($data);

            if (data_get($data, 'meta')) {
                $proceedingData->setManyMeta($data['meta']);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $proceedingData;
    }
}