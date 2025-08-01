<?php

namespace App\Actions\Conferences;

use App\Models\Conference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConferenceUpdateAction
{
    use AsAction;

    public function handle(Conference $conference, array $data)
    {
        try {
            DB::beginTransaction();

            $conference->update($data);

            if (data_get($data, 'meta')) {
                $conference->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $conference;
    }
}
