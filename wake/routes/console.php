<?php

use Illuminate\Support\Facades\Schedule;
use Wake\Maintenance\Maintenance;

// With "maintenance.mode" set to "cron", a cron job calling
// `php wake/artisan schedule:run` every minute runs maintenance instead of
// page views.

if (config('wake.maintenance.mode') === 'cron') {
    Schedule::call(fn () => app(Maintenance::class)->run())->everyMinute()->name('wake:maintenance');
}
