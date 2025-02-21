<?php

namespace App\Utils\UpgradeSchemas;

use App\Models\Enums\SubmissionStage;
use App\Models\Enums\SubmissionStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class Upgrade122 extends UpgradeBase
{
    public function run(): void
    {
        $this->migrate();
    }

    protected function migrate(): void
    {
        DB::table('submissions')
            ->where('status', 'On Payment')
            ->update([
                'status' => SubmissionStatus::OnPresentation,
            ]);

        DB::table('submissions')
            ->where('status', 'Payment Declined')
            ->update([
                'status' => SubmissionStatus::Declined,
            ]);

        DB::table('submissions')
            ->where('stage', 'Payment')
            ->update([
                'stage' => SubmissionStage::Presentation,
            ]);

    }
}
