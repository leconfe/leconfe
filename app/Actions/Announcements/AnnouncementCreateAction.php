<?php

namespace App\Actions\Announcements;

use App\Facades\Setting;
use App\Models\Announcement;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class AnnouncementCreateAction
{
    use AsAction;

    public function handle($data, $sendEmail = false)
    {
        try {
            DB::beginTransaction();

            // Handle multilanguage data
            $primaryLocale = Setting::get('default_language', app()->getLocale());
            $processedData = $data;

            // Extract title for primary locale and save to main field
            if (isset($data['title'][$primaryLocale])) {
                $processedData['title'] = $data['title'][$primaryLocale];
            }

            // Prepare meta data for multilanguage
            $metaData = data_get($data, 'meta', []);

            // Store multilanguage title in meta
            if (isset($data['title']) && is_array($data['title'])) {
                $metaData['title'] = $data['title'];
            }

            $announcement = Announcement::create($processedData);

            if ($metaData) {
                $announcement->setManyMeta($metaData);
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
