<?php

use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\RunHealthChecksCommand;

Schedule::command(RunHealthChecksCommand::class)->everyMinute();
