<?php

namespace App\Data;

/**
 * Uitkomst van de position size calculator. Alle bedragen in centen.
 */
final readonly class PositionSize
{
    public function __construct(
        public int $shares,
        public bool $isShort,
        public int $entryCents,
        public int $stopCents,
        public ?int $targetCents,
        public int $riskPerShareCents,
        public int $riskBudgetCents,
        public int $positionValueCents,
        public int $maxLossCents,
        public ?int $potentialProfitCents,
        public ?float $riskReward,
        public bool $cappedByBuyingPower,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shares' => $this->shares,
            'is_short' => $this->isShort,
            'risk_per_share' => $this->riskPerShareCents / 100,
            'risk_budget' => $this->riskBudgetCents / 100,
            'position_value' => $this->positionValueCents / 100,
            'max_loss' => $this->maxLossCents / 100,
            'potential_profit' => $this->potentialProfitCents === null ? null : $this->potentialProfitCents / 100,
            'risk_reward' => $this->riskReward,
            'capped_by_buying_power' => $this->cappedByBuyingPower,
        ];
    }
}
