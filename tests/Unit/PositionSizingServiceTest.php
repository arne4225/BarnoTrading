<?php

use App\Exceptions\InvalidOrderException;
use App\Services\PositionSizingService;

beforeEach(fn () => $this->sizing = new PositionSizingService);

it('sizes a long from risk percentage and stop-loss', function () {
    // $100.000 account, 1% risico = $1.000; instap $50, stop $48 => $2 risico per aandeel.
    $size = $this->sizing->calculate(10_000_000, 100, 5000, 4800, 5600);

    expect($size->shares)->toBe(500)
        ->and($size->isShort)->toBeFalse()
        ->and($size->riskBudgetCents)->toBe(100_000)
        ->and($size->positionValueCents)->toBe(2_500_000)
        ->and($size->maxLossCents)->toBe(100_000)
        ->and($size->potentialProfitCents)->toBe(300_000)
        ->and($size->riskReward)->toBe(3.0);
});

it('floors the number of shares so the risk is never exceeded', function () {
    $size = $this->sizing->calculate(1_000_000, 100, 10000, 9700); // budget $100, $3 per aandeel

    expect($size->shares)->toBe(33)
        ->and($size->maxLossCents)->toBeLessThanOrEqual($size->riskBudgetCents);
});

it('detects a short when the stop is above the entry', function () {
    $size = $this->sizing->calculate(10_000_000, 50, 5000, 5200, 4400);

    expect($size->isShort)->toBeTrue()
        ->and($size->shares)->toBe(250)
        ->and($size->potentialProfitCents)->toBe(250 * 600)
        ->and($size->riskReward)->toBe(3.0);
});

it('caps a long by buying power', function () {
    $size = $this->sizing->calculate(10_000_000, 100, 5000, 4800, null, 1_000_000);

    expect($size->shares)->toBe(200)
        ->and($size->cappedByBuyingPower)->toBeTrue()
        ->and($size->riskReward)->toBeNull()
        ->and($size->potentialProfitCents)->toBeNull();
});

it('returns zero shares when the risk budget is smaller than the risk of one share', function () {
    expect($this->sizing->calculate(100_000, 100, 5000, 4000)->shares)->toBe(1)
        ->and($this->sizing->calculate(10_000, 100, 5000, 4000)->shares)->toBe(0);
});

it('rejects invalid levels', function (int $entry, int $stop, ?int $target) {
    $this->sizing->calculate(10_000_000, 100, $entry, $stop, $target);
})->with([
    'stop equals entry' => [5000, 5000, null],
    'zero stop' => [5000, 0, null],
    'target on the loss side of a long' => [5000, 4800, 4900],
    'target on the loss side of a short' => [5000, 5200, 5100],
])->throws(InvalidOrderException::class);
