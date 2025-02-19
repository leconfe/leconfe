<?php

namespace App\Utils\UpgradeSchemas;

use App\Frontend\ScheduledConference\Pages\ParticipantRegistrationSuccess;
use App\Managers\PaymentManager;
use App\Models\NavigationMenuItem;
use App\Models\Participant;
use App\Models\Payment;
use App\Models\PaymentFee;
use App\Models\RegistrationType;
use App\Models\Scopes\ConferenceScope;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class Upgrade121 extends UpgradeBase
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
