<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Participant;
use App\Models\Payment;
use App\Models\Submission;
use Exception;
use Illuminate\Support\Facades\Artisan;

class Upgrade130Beta3 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();
        $this->cleanupPayment();
    }

    protected function cleanupPayment()
    {
        Submission::setAllGlobalScopes([]);
        Participant::setAllGlobalScopes([]);

        Payment::query()
            ->whereDoesntHaveMorph('model', [Submission::class, Participant::class])
            ->lazy()
            ->each
            ->delete();
    }

    protected function migrate(): void
    {
        Artisan::call('migrate', [
            '--force' => true,
        ]);
    }
}
