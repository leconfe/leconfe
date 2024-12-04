<?php

namespace App\Console;

use App\Actions;
use App\Actions\Metrics\MoveMetricLogToQueues;
use App\Actions\Metrics\ProcessMetricTrackQueues;
use App\Actions\Submissions\RemoveDeletedDiscussion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by the application.
     *
     * @var array
     */
    protected $commands = [
        Actions\Permissions\PermissionPopulateAction::class,
        Actions\Leconfe\InstallAction::class,
        Actions\Leconfe\UpgradeAction::class,
        Actions\Leconfe\CheckVersionAction::class,
        Actions\Leconfe\CheckLatestVersion::class,
        Actions\Leconfe\GetUpgradeActionHistory::class,
        Actions\Leconfe\Relink::class,
        Actions\Metrics\ProcessMetricTrackQueues::class,
        Actions\Metrics\MoveMetricLogToQueues::class,

    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            RemoveDeletedDiscussion::run();
        })
        ->monthly()
        ->name('Running Cleaner');

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
