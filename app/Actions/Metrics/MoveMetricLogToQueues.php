<?php

namespace App\Actions\Metrics;

use App\Facades\Metric;
use App\Models\Submission;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

use function Laravel\Prompts\spin;

class MoveMetricLogToQueues
{
    use AsAction;

    public function handle()
    {
        Metric::moveLogToQueues();
    }

    public function asCommand(Command $command): void
    {
        spin(
            message: 'Moving metric logs files to queues...',
            callback: fn () => $this->handle(),
        );

        $this->handle();
    }

    public function getCommandSignature(): string
    {
        return 'leconfe:metric:move-track-to-queues';
    }
}
