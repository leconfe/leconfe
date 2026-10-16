<?php

namespace App\Actions\Tracks;

use App\Models\Track;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackCreateAction
{
    use AsAction;

    public function handle($data): Track
    {
        try {
            DB::beginTransaction();

            $track = new Track;
            $data = $track->syncTranslatableState($data);
            $track->fill($data)->save();

            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                $track->setManyMeta($data['meta']);
            }

            $track->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $track;
    }
}
