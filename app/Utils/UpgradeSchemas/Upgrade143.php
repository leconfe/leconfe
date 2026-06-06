<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Enums\UserRole;
use App\Models\Permission;
use App\Models\ScheduledConference;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\Role;

class Upgrade143 extends UpgradeBase
{
    public function run(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
