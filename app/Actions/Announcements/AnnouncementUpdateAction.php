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

            $announcement->update($data);

            if (data_get($data, 'meta')) {
                $announcement->setManyMeta(data_get($data, 'meta'));
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $announcement;
    }
}
