<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Daily pipeline: refresh prices -> recompute strategy state -> alert
        // on transitions. 21:30 UTC is after both the Warsaw close (15:00 UTC)
        // and the US close (20:00/21:00 UTC), so one run covers all markets.
        $schedule->command('stocks:fetch --symbols=db')
            ->weekdays()->at('21:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('signals:compute')
            ->weekdays()->at('21:50')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('alerts:discord')
            ->weekdays()->at('21:55')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/pipeline.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
