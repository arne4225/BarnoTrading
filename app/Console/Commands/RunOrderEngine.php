<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:engine {--once : Controleer één keer en stop} {--interval= : Seconden tussen controles}')]
#[Description('Controleert open orders tegen de actuele koersen en voert ze uit')]
class RunOrderEngine extends Command
{
    public function handle(OrderService $orders): int
    {
        $interval = max(1, (int) ($this->option('interval') ?: config('trading.engine_interval_seconds')));

        do {
            $filled = $orders->processOpenOrders();

            if ($filled > 0 || $this->option('once')) {
                $this->line(now()->toTimeString()." — {$filled} order(s) uitgevoerd");
            }

            if ($this->option('once')) {
                break;
            }

            sleep($interval);
        } while (true);

        return self::SUCCESS;
    }
}
