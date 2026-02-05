<?php

namespace App\Actions\Proceedings;

use App\Models\Proceeding;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProceedingUpdateAction
{
    use AsAction;

    public function handle(Proceeding $proceeding, array $data): Proceeding
    {
        try {
            DB::beginTransaction();

            $proceeding->update($data);
            if (data_get($data, 'meta')) {
                $proceeding->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $proceeding;
    }
}
