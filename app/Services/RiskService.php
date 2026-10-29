<?php

namespace App\Services;

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Exceptions\InvalidOrderException;
use App\Models\User;
use App\Services\MarketData\MarketDataService;

/**
 * Risicobewaking: waarschuwingen bij slecht risicomanagement en de optionele
 * dagelijkse verlieslimiet. Waarschuwingen blokkeren nooit; de verlieslimiet wel.
 */
class RiskService
{
    public function __construct(
        private readonly PortfolioService $portfolio,
        private readonly MarketDataService $marketData,
    ) {}

    /**
     * @return list<string>
     */
    public function assess(User $user, PlaceOrderData $data): array
    {
        $symbol = MarketDataService::normalizeSymbol($data->symbol);
        $quote = $this->marketData->quotes([$symbol])[$symbol] ?? null;
        $position = $this->portfolio->positionFor($user, $symbol);
        $current = $position?->quantity ?? 0;

        if ($this->reducesPosition($data, $current) || $quote === null) {
            return [];
        }

        $entry = $data->limitPriceCents ?? $data->stopPriceCents ?? $quote->priceCents;
        $warnings = [];

        if ($user->warn_without_stop_loss && $data->stopLossCents === null && $data->type->value !== 'trailing_stop') {
            $warnings[] = 'Deze order heeft geen stop-loss: je verlies is onbegrensd.';
        }

        if ($data->stopLossCents !== null && $data->takeProfitCents !== null && $data->stopLossCents !== $entry) {
            $risk = abs($entry - $data->stopLossCents);
            $ratio = abs($data->takeProfitCents - $entry) / $risk;
            if ($ratio * 100 < $user->min_risk_reward_hundredths) {
                $warnings[] = sprintf(
                    'Risk/reward is 1:%s, onder je minimum van 1:%s.',
                    number_format($ratio, 2),
                    number_format($user->min_risk_reward_hundredths / 100, 2),
                );
            }
        }

        $equity = $this->portfolio->summary($user)->equityCents;
        if ($equity > 0) {
            $positionValue = (abs($current) + $data->quantity) * $entry;
            $percent = $positionValue / $equity * 100;
            if ($percent > $user->max_position_percent) {
                $warnings[] = sprintf(
                    'Deze positie is %s%% van je account, boven je limiet van %d%%.',
                    number_format($percent, 1),
                    $user->max_position_percent,
                );
            }
        }

        return $warnings;
    }

    /**
     * Blokkeert nieuwe (positie-vergrotende) orders na het bereiken van de verlieslimiet.
     * Zodra de limiet geraakt is blijft de blokkade tot het einde van die handelsdag staan.
     *
     * @throws InvalidOrderException
     */
    public function assertWithinDailyLossLimit(User $user, PlaceOrderData $data): void
    {
        if ($user->daily_loss_limit_bps === null) {
            return;
        }

        $symbol = MarketDataService::normalizeSymbol($data->symbol);
        $current = $this->portfolio->positionFor($user, $symbol)?->quantity ?? 0;
        if ($this->reducesPosition($data, $current)) {
            return;
        }

        $today = now(config('trading.timezone'))->toDateString();

        if ($user->trading_blocked_on !== $today) {
            $summary = $this->portfolio->summary($user);
            $limit = intdiv($this->portfolio->dailyBaselineCents($user) * $user->daily_loss_limit_bps, 10000);
            if ($summary->dailyPnlCents > -$limit) {
                return;
            }

            $user->update(['trading_blocked_on' => $today]);
        }

        throw new InvalidOrderException(sprintf(
            'Dagelijkse verlieslimiet van %s%% bereikt: nieuwe orders zijn geblokkeerd tot de volgende handelsdag. Sluiten van posities blijft mogelijk.',
            number_format($user->daily_loss_limit_bps / 100, 2),
        ));
    }

    private function reducesPosition(PlaceOrderData $data, int $currentQuantity): bool
    {
        return ($data->side === OrderSide::Buy && $currentQuantity < 0)
            || ($data->side === OrderSide::Sell && ! $data->isShort && $currentQuantity > 0);
    }
}
