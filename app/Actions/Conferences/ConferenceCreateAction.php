<?php

namespace App\Actions\Conferences;

use App\Models\Conference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ConferenceCreateAction
{
    use AsAction;

    public function handle(array $data): Conference
    {
        try {
            DB::beginTransaction();

            $conferenceData = data_get($data, 'conference_id')
                ? ConferenceCloneAction::run($data)
                : Conference::create($data);

            if (data_get($data, 'meta')) {
                $conferenceData->setManyMeta($data['meta']);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }

        return $conferenceData;
    }
}
