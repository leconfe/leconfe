<?php

namespace App\Actions\Speakers;

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

            $data = $speakerRole->syncTranslatableState($data);

            $speakerRole->update($data);

            $speakerRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $speakerRole;
    }
}
