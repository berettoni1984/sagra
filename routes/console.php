<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Esegue i controlli di stato e ne salva l'esito, che alimenta la pagina
// /health e le notifiche in caso di problemi.
Schedule::command(RunHealthChecksCommand::class)->everyMinute();

// Battito dello scheduler: se il cron si ferma, ScheduleCheck lo rileva
// perché questo battito smette di aggiornarsi.
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();
