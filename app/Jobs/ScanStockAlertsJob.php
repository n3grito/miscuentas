<?php

namespace App\Jobs;

use App\Services\StockAlertService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ScanStockAlertsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $warehouseId = null)
    {
    }

    public function handle(StockAlertService $service): void
    {
        $service->scan($this->warehouseId);
    }
}