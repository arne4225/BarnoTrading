<?php

namespace App\Console\Commands;

use App\Services\PortfolioService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trading:snapshot')]
#[Description('Slaat de dagelijkse portfoliowaarde van alle gebruikers op (basis voor dagelijkse P&L)')]
class SnapshotPortfolios extends Command
{
    public function handle(PortfolioService $portfolio): int
    {
        $this->info($portfolio->snapshotAll().' portfolio(s) opgeslagen');

        return self::SUCCESS;
    }
}
