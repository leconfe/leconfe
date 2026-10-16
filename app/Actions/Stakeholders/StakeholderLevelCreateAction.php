<?php

namespace App\Actions\Stakeholders;

use App\Models\StakeholderLevel;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StakeholderLevelCreateAction
{
    use AsAction;

    public function handle($data): StakeholderLevel
    {
        try {
            DB::beginTransaction();

            $record = new StakeholderLevel;
            $data = $record->syncTranslatableState($data);
            $record->fill($data)->save();

            $record->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $record;
    }
}
