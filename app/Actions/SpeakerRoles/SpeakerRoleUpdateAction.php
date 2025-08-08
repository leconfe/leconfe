<?php

namespace App\Actions\SpeakerRoles;

use App\Models\SpeakerRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SpeakerRoleUpdateAction
{
    use AsAction;

    public function handle(SpeakerRole $speakerRole, array $data): SpeakerRole
    {
        try {
            DB::beginTransaction();

            $speakerRole->update($data);

            if (data_get($data, 'meta')) {
                $speakerRole->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $speakerRole;
    }
}