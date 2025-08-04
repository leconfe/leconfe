<?php

namespace App\Actions\Proceedings;

use App\Models\Proceeding;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProceedingCreateAction
{
    use AsAction;

    public function handle(array $data): Proceeding
    {
        try {
            DB::beginTransaction();

            $proceedingData = Proceeding::create($data);

            if (data_get($data, 'meta')) {
                $proceedingData->setManyMeta($data['meta']);
            }

            if (data_get($data, 'cover')) {
                foreach (data_get($data, 'cover', []) as $file) {
                    $proceedingData->addMedia($file)->toMediaCollection('cover');
                }
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $proceedingData;
    }


}