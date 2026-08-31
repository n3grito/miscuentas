<?php

use App\Jobs\ScanStockAlertsJob;
use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::job(new ScanStockAlertsJob)->dailyAt('06:00')->withoutOverlapping();

/*
 * Backup automático diario:
 * ejecuta backup:run y backup:clean a las 02:00 AM.
 * Requiere configurar BACKUP_NOTIFY_EMAIL en .env para notificaciones.
 */
Schedule::command('backup:run', ['--only-db' => true])->dailyAt('02:00')->name('backup-run')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:30')->name('backup-clean')->withoutOverlapping();

/*
 * Poda eficiente del registro de auditoría:
 * elimina entradas anteriores a los días de retención configurados
 * (ajuste logs.retention_days, por defecto 180).
 */
if (Schema::hasTable('settings')) {
    Schedule::call(function () {
        $days = (int) (Setting::get('logs', 'retention_days', 180) ?: 180);

        Artisan::call('activitylog:clean', ['--days' => max(7, $days)]);
    })->dailyAt('03:30')->name('poda-logs-auditoria')->withoutOverlapping();
}
