<?php

namespace App\Actions\Metrics;

use App\Facades\Metric;
use App\Models\Submission;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class MoveMetricLogToQueues
{
    use AsAction;

    public function handle()
    {
        Metric::moveLogToQueues();
    }

    public function asCommand(Command $command): void
    {
        $command->info('Moving metric logs files to queues...');

        $this->handle();
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:metric:move-track-to-queues';
    }
}
