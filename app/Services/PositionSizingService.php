<?php

namespace App\Services;

use App\Data\PositionSize;
use App\Exceptions\InvalidOrderException;

/**
 * Position size calculator: aantal aandelen op basis van risico-% en stop-loss.
 * Rekent volledig in gehele centen en basispunten.
 */
class PositionSizingService
{
    /**
     * @param  int  $riskBps  risico per trade in basispunten van het account (100 = 1%)
     * @param  int|null  $buyingPowerCents  beperkt het aantal tot wat betaalbaar is (alleen bij long)
     *
     * @throws InvalidOrderException
     */
    public function calculate(
        int $equityCents,
        int $riskBps,
        int $entryCents,
        int $stopCents,
        ?int $targetCents = null,
        ?int $buyingPowerCents = null,
    ): PositionSize {
        if ($entryCents < 1 || $stopCents < 1) {
            throw new InvalidOrderException('Instap en stop-loss moeten positief zijn.');
        }
        if ($entryCents === $stopCents) {
            throw new InvalidOrderException('De stop-loss mag niet gelijk zijn aan de instapprijs.');
        }

        $isShort = $stopCents > $entryCents;
        $riskPerShare = abs($entryCents - $stopCents);
        $riskBudget = intdiv(max(0, $equityCents) * $riskBps, 10000);

        if ($targetCents !== null && ($isShort ? $targetCents >= $entryCents : $targetCents <= $entryCents)) {
            throw new InvalidOrderException('Take-profit moet aan de winstkant van de instapprijs liggen.');
        }

        $shares = intdiv($riskBudget, $riskPerShare);
        $capped = false;
        if (! $isShort && $buyingPowerCents !== null) {
            $affordable = intdiv(max(0, $buyingPowerCents), $entryCents);
            if ($affordable < $shares) {
                $shares = $affordable;
                $capped = true;
            }
        }

        $rewardPerShare = $targetCents === null ? null : abs($targetCents - $entryCents);

        return new PositionSize(
            shares: $shares,
            isShort: $isShort,
            entryCents: $entryCents,
            stopCents: $stopCents,
            targetCents: $targetCents,
            riskPerShareCents: $riskPerShare,
            riskBudgetCents: $riskBudget,
            positionValueCents: $shares * $entryCents,
            maxLossCents: $shares * $riskPerShare,
            potentialProfitCents: $rewardPerShare === null ? null : $shares * $rewardPerShare,
            riskReward: $rewardPerShare === null ? null : round($rewardPerShare / $riskPerShare, 2),
            cappedByBuyingPower: $capped,
        );
    }
}
