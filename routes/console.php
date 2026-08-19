<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('nominal:dispatch-due-checks')->everyMinute();
Schedule::command('nominal:rollup-aggregates')->hourly();
Schedule::command('nominal:prune-results')->hourly();
