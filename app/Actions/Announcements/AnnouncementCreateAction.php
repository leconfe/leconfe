<?php

namespace App\Actions\Announcements;

use App\Models\Announcement;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnouncementCreateAction
{
    use AsAction;

    public function handle(array $data, bool $sendEmail = false)
    {
        try {
            DB::beginTransaction();

            $announcement = Announcement::create($data);
            if (data_get($data, 'meta')) {
                $announcement->setManyMeta(data_get($data, 'meta'));
            }

            if ($sendEmail) {
                AnnouncementBroadcastMail::dispatch($announcement);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            throw $th;
        }

        return $announcement;
    }
}
