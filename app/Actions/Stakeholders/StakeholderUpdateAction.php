<?php

namespace App\Actions\Stakeholders;

use App\Models\Stakeholder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StakeholderUpdateAction
{
    use AsAction;

    public function handle(Stakeholder $record, array $data): Stakeholder
    {
        try {
            DB::beginTransaction();

            $record->update($data);

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
