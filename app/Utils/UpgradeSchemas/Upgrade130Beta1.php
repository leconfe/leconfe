<?php

namespace App\Utils\UpgradeSchemas;

use Illuminate\Support\Facades\Artisan;

class Upgrade130Beta1 extends UpgradeBase
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
