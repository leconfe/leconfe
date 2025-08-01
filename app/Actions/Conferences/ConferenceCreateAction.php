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

            
            if (data_get($data, 'conference_id')) {
                $conferenceData = ConferenceCloneAction::run($data);
                 // kalo datanya dalam bentuk array (seperti multilanguage fields), simpan sebagai meta
            } else {
                // Jika name adalah array (multilanguage), pindahkan ke meta
                $meta = [];
                if (isset($data['name']) && is_array($data['name'])) {
                    $meta['name'] = $data['name'];
                    $data['name'] = null;
                }

                $conferenceData = Conference::create($data);

                if (!empty($meta)) {
                    $conferenceData->setManyMeta($meta);
                }
            }

            // Extra meta dari form
            if (isset($data['meta'])) {
                $conferenceData->setManyMeta($data['meta']);
            }

            DB::commit();
            return $conferenceData;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

}
