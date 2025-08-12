<?php

namespace App\Actions\SpeakerRoles;

use App\Models\SpeakerRole;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class SpeakerRoleCreateAction
{
    use AsAction;

    public function handle(array $data): SpeakerRole
    {
        try {
            DB::beginTransaction();

            $speakerRole = SpeakerRole::create($data);

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