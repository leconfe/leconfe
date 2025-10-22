<?php

namespace App\Utils\UpgradeSchemas;

use App\Actions\ScheduledConferences\ScheduledConferenceRegisterEntityAction;
use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

class Upgrade132 extends UpgradeBase
{
    public function run(): void
    {
         Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
