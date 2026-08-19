<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nominal:dispatch-due-checks')
    ->everyFiveSeconds()
    ->withoutOverlapping(1);
Schedule::command('nominal:rollup-aggregates')->hourly();
Schedule::command('nominal:prune-results')->hourly();
