<?php

namespace App\Actions\Speakers;

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

            $speakerRole = new SpeakerRole;
            $data = $speakerRole->syncTranslatableState($data);
            $speakerRole->fill($data)->save();

            $speakerRole->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $speakerRole;
    }
}
