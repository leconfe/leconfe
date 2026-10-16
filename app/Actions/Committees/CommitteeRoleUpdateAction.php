<?php

namespace App\Actions\Committees;

use App\Models\CommitteeRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CommitteeRoleUpdateAction
{
    use AsAction;

    public function handle(CommitteeRole $committeeRole, array $data): CommitteeRole
    {
        try {
            DB::beginTransaction();

            $data = $committeeRole->syncTranslatableState($data);

            $committeeRole->update($data);

            $committeeRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $committeeRole;
    }
}
