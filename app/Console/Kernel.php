<?php

namespace App\Console;

use App\Console\Commands\Vp\CancelExpiredOrder;
use App\Console\Commands\Vp\FlipCancelExpiredOrder;
use App\Console\Commands\Vp\QueryPendingOrder;
use App\Console\Commands\Vp\TransData;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        QueryPendingOrder::class,
        CancelExpiredOrder::class,
        FlipCancelExpiredOrder::class,
        TransData::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('QueryPendingOrder')->everyMinute()->withoutOverlapping(2)->runInBackground();
        $schedule->command('CancelExpiredOrder')->everyMinute()->withoutOverlapping(2)->runInBackground();
        $schedule->command('FlipCancelExpiredOrder')->everyMinute()->withoutOverlapping(2)->runInBackground();
    }
}
