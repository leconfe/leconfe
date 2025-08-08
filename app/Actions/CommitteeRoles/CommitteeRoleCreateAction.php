<?php

namespace App\Actions\CommitteeRoles;

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

            $committeeRole = CommitteeRole::create($data);

            if (data_get($data, 'meta')) {
                $committeeRole->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $committeeRole;
    }
}