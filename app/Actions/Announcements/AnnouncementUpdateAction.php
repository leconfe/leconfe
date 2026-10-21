<?php

namespace App\Actions\Announcements;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnouncementUpdateAction
{
    use AsAction;

    public function handle(Announcement $announcement, array $data): Model
    {
        try {
            DB::beginTransaction();

            $data = $announcement->syncTranslatableState($data);

            $announcement->update($data);

            if (data_get($data, 'meta')) {
                $announcement->setManyMeta(data_get($data, 'meta'));
            }

            $announcement->persistTranslatableState();

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $announcement;
    }
}
