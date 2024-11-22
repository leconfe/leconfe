<?php

namespace App\Actions\Stakeholders;

use App\Models\Stakeholder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StakeholderCreateAction
{
    use AsAction;

    public function handle($data): Stakeholder
    {
        try {
            DB::beginTransaction();

            $record = Stakeholder::create($data);

            if ($meta = data_get($data, 'meta')) {
                $record->setManyMeta($meta);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $record;
    }
}
