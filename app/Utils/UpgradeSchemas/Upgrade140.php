<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\ScheduledConference;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Enums\UserRole;
use App\Models\Role;

class Upgrade140 extends UpgradeBase
{
    public function run(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
