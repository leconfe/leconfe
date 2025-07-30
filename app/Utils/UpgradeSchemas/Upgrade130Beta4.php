<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Support\Facades\Artisan;

class Upgrade130Beta4 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();
    }

    protected function migrate(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
