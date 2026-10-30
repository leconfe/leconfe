<?php

namespace App\Actions\Committees;

use App\Models\CommitteeRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CommitteeRoleCreateAction
{
    use AsAction;

    public function handle(array $data): CommitteeRole
    {
        try {
            DB::beginTransaction();

            $committeeRole = new CommitteeRole;
            $data = $committeeRole->syncTranslatableState($data);
            $committeeRole->fill($data)->save();

            $committeeRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $committeeRole;
    }
}
