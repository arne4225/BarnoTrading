<?php

use App\Services\MarketData\MarketCalendar;
use Carbon\CarbonImmutable;

beforeEach(function () {
    config(['trading.ignore_market_hours' => false]);
    $this->calendar = new MarketCalendar;
});

function eastern(string $datetime): CarbonImmutable
{
    return CarbonImmutable::parse($datetime, 'America/New_York');
}

it('is open during regular hours on a weekday', function () {
    expect($this->calendar->isOpen(eastern('2026-10-29 09:30:00')))->toBeTrue()
        ->and($this->calendar->isOpen(eastern('2026-10-29 15:59:59')))->toBeTrue();
});

it('is closed before open, at close, at night and on weekends', function () {
    expect($this->calendar->isOpen(eastern('2026-10-29 09:29:59')))->toBeFalse()
        ->and($this->calendar->isOpen(eastern('2026-10-29 16:00:00')))->toBeFalse()
        ->and($this->calendar->isOpen(eastern('2026-10-31 11:00:00')))->toBeFalse();
});

it('is closed on holidays', function () {
    expect($this->calendar->isOpen(eastern('2026-11-26 11:00:00')))->toBeFalse();
});

it('respects the Eastern timezone regardless of input timezone', function () {
    expect($this->calendar->isOpen(CarbonImmutable::parse('2026-10-29 13:45:00', 'UTC')))->toBeTrue();
});

it('reports the next open after a Friday close as Monday', function () {
    $clock = $this->calendar->clock(eastern('2026-10-30 17:00:00'));

    expect($clock->isOpen)->toBeFalse()
        ->and($clock->nextOpen->setTimezone('America/New_York')->toDateTimeString())->toBe('2026-11-02 09:30:00');
});

it('reports the close of today while open', function () {
    $clock = $this->calendar->clock(eastern('2026-10-29 10:00:00'));

    expect($clock->isOpen)->toBeTrue()
        ->and($clock->nextClose->setTimezone('America/New_York')->toDateTimeString())->toBe('2026-10-29 16:00:00');
});

it('always reports open when hours are ignored', function () {
    config(['trading.ignore_market_hours' => true]);

    expect($this->calendar->isOpen(eastern('2026-10-31 03:00:00')))->toBeTrue();
});
