<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bookings:complete-paid')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('fieldly:queue-reminders')->everyFiveMinutes()->withoutOverlapping();
