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
        // Early pipeline for Warsaw-listed symbols only: WSE closes at
        // 15:00/16:00 UTC (summer/winter), so .WA alerts can go out hours
        // before the US-close run below instead of waiting for it
        $schedule->command('stocks:fetch --symbols=db:.WA')
            ->weekdays()->at('16:15')
            ->withoutOverlapping()
            ->before($this->stampLog('stocks:fetch --symbols=db:.WA'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('signals:compute')
            ->weekdays()->at('16:30')
            ->withoutOverlapping()
            ->before($this->stampLog('signals:compute'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('alerts:discord')
            ->weekdays()->at('16:35')
            ->withoutOverlapping()
            ->before($this->stampLog('alerts:discord'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        // Daily pipeline: refresh prices -> recompute strategy state -> alert
        // on transitions. 21:30 UTC is after both the Warsaw close (15:00 UTC)
        // and the US close (20:00/21:00 UTC), so one run covers all markets.
        $schedule->command('stocks:fetch --symbols=db')
            ->weekdays()->at('21:30')
            ->withoutOverlapping()
            ->before($this->stampLog('stocks:fetch --symbols=db'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('signals:compute')
            ->weekdays()->at('21:50')
            ->withoutOverlapping()
            ->before($this->stampLog('signals:compute'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));

        $schedule->command('alerts:discord')
            ->weekdays()->at('21:55')
            ->withoutOverlapping()
            ->before($this->stampLog('alerts:discord'))
            ->appendOutputTo(storage_path('logs/pipeline.log'));
    }

    /**
     * Append a "[2026-07-14 16:30 UTC] signals:compute" header line to the
     * pipeline log right before a job runs, so the runs sharing the log
     * are distinguishable from each other.
     */
    private function stampLog(string $command): \Closure
    {
        return function () use ($command) {
            file_put_contents(
                storage_path('logs/pipeline.log'),
                PHP_EOL . '[' . now()->format('Y-m-d H:i') . ' UTC] ' . $command . PHP_EOL,
                FILE_APPEND
            );
        };
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
