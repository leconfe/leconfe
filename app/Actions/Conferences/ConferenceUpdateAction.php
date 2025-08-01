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

            $meta = [];
            if (isset($data['name']) && is_array($data['name'])) {
                $meta['name'] = $data['name'];
                $data['name'] = null;
            }

            $conference->update($data);

            if (!empty($meta)) {
                $conference->setManyMeta($meta);
            }

            if (isset($data['meta'])) {
                $conference->setManyMeta($data['meta']);
            }

            DB::commit();
            return $conference;
        } catch (\Throwable $th) {
            DB::rollBack();
            throw $th;
        }
    }

}
