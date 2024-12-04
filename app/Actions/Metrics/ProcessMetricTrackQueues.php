<?php

namespace App\Actions\Metrics;

use App\Facades\Metric;
use App\Models\Submission;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessMetricTrackQueues
{
    use AsAction;

    public function handle()
    {
        Metric::processQueues();
    }

    public function asCommand(Command $command): void
    {
        $this->handle();
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:metric:process-queues';
    }
}
