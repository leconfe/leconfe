<?php

namespace App\Actions\Metrics;

use App\Facades\Metric;
use App\Models\Submission;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;

class ProcessMetricLogQueues
{
    use AsAction;

    public function handle()
    {
        Metric::processQueues();
    }

    public function asCommand(Command $command): void
    {
        spin(
            message: 'Processing metric track queues ...',
            callback: fn () => $this->handle(),
        );
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:metric:process-queues';
    }
}
