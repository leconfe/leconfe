<?php

namespace App\Console;

use App\Actions;
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
        Actions\Leconfe\QuickInstall::class,
        Actions\Leconfe\GetUpgradeActionHistory::class,
        Actions\Leconfe\Relink::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call(function () {
            RemoveDeletedDiscussion::run();
        })->cron(
            sprintf(
                '*/0 */0 */%d * *',
                config('cleaner.day_interval')
            )
        )->name('Remove deleted discussions');
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
