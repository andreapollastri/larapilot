<?php

namespace App\Providers;

use App\Console\Commands\PruneWaitlists;
use App\Console\Commands\SendReminders;
use App\Jobs\GenerateMonthlyInvoices;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FernwayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views'), 'fernway');
        Route::middleware('web')->group(base_path('routes/web.php'));

        if ($this->app->runningInConsole()) {
            $this->commands([SendReminders::class, PruneWaitlists::class]);
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('fernway:send-reminders')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();
            $schedule->command('model:prune')->dailyAt('03:30');
            $schedule->command('fernway:prune-waitlists')->hourly()->runInBackground();
            $schedule->command('queue:prune-failed --hours=168')->daily();
            $schedule->call(fn () => Cache::forget('timetable'))->name('flush-timetable-cache')->dailyAt('00:05');
            $schedule->job(new GenerateMonthlyInvoices)->monthlyOn(1, '06:00')->environments(['production']);
        });
    }
}
