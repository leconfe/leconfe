<?php

namespace App\Utils\UpgradeSchemas;

use App\Actions\ScheduledConferences\ScheduledConferenceRegisterEntityAction;
use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class Upgrade133 extends UpgradeBase
{
    public function run(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
        
        DB::table('scheduled_conferences')
            ->whereIn('state', [2, 3, 4])
            ->update(['is_published' => 1]);
    }
}
