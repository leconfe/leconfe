<?php

namespace App\Actions\Tracks;

use App\Models\Track;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TrackUpdateAction
{
    use AsAction;

    public function handle(Track $track, array $data): Track
    {
        try {
            DB::beginTransaction();

            $data = $track->syncTranslatableState($data);

            $track->update($data);

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
