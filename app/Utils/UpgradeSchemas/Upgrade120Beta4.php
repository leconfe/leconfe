<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Permission;
use App\Models\Review;
use App\Models\Track;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class Upgrade120Beta4 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();
    }

    protected function migrate()
    {
        Artisan::call('migrate');
    }
}
