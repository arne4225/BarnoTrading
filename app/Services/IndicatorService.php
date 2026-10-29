<?php

namespace App\Services;

use App\Data\Candle;

/**
 * Technische indicatoren. Rekent op floats in dollars omdat het weergave-reeksen
 * zijn (geen boekhouding); invoer zijn candles in centen. Elke reeks heeft
 * dezelfde lengte als de invoer, met null waar de indicator nog niet bestaat.
 */
class IndicatorService
{
    /**
     * @param  list<float>  $values
     * @return list<?float>
     */
    public function sma(array $values, int $period): array
    {
        $result = array_fill(0, count($values), null);
        if ($period < 1 || count($values) < $period) {
            return $result;
        }

        $sum = array_sum(array_slice($values, 0, $period));
        $result[$period - 1] = $sum / $period;
        for ($i = $period; $i < count($values); $i++) {
            $sum += $values[$i] - $values[$i - $period];
            $result[$i] = $sum / $period;
        }

        return $result;
    }

    /**
     * EMA met de SMA van de eerste $period waarden als startwaarde.
     *
     * @param  list<float>  $values
     * @return list<?float>
     */
    public function ema(array $values, int $period): array
    {
        $result = array_fill(0, count($values), null);
        if ($period < 1 || count($values) < $period) {
            return $result;
        }

        $k = 2 / ($period + 1);
        $ema = array_sum(array_slice($values, 0, $period)) / $period;
        $result[$period - 1] = $ema;
        for ($i = $period; $i < count($values); $i++) {
            $ema = $values[$i] * $k + $ema * (1 - $k);
            $result[$i] = $ema;
        }

        return $result;
    }

    /**
     * RSI met Wilder-smoothing.
     *
     * @param  list<float>  $values
     * @return list<?float>
     */
    public function rsi(array $values, int $period = 14): array
    {
        $result = array_fill(0, count($values), null);
        if ($period < 1 || count($values) <= $period) {
            return $result;
        }

        $gain = $loss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $values[$i] - $values[$i - 1];
            $gain += max($change, 0);
            $loss += max(-$change, 0);
        }
        $avgGain = $gain / $period;
        $avgLoss = $loss / $period;
        $result[$period] = $this->rsiValue($avgGain, $avgLoss);

        for ($i = $period + 1; $i < count($values); $i++) {
            $change = $values[$i] - $values[$i - 1];
            $avgGain = ($avgGain * ($period - 1) + max($change, 0)) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + max(-$change, 0)) / $period;
            $result[$i] = $this->rsiValue($avgGain, $avgLoss);
        }

        return $result;
    }

    /**
     * @param  list<float>  $values
     * @return array{macd: list<?float>, signal: list<?float>, histogram: list<?float>}
     */
    public function macd(array $values, int $fast = 12, int $slow = 26, int $signalPeriod = 9): array
    {
        $count = count($values);
        $fastEma = $this->ema($values, $fast);
        $slowEma = $this->ema($values, $slow);

        $macd = array_fill(0, $count, null);
        for ($i = 0; $i < $count; $i++) {
            if ($fastEma[$i] !== null && $slowEma[$i] !== null) {
                $macd[$i] = $fastEma[$i] - $slowEma[$i];
            }
        }

        $first = array_key_first(array_filter($macd, fn ($v) => $v !== null));
        $signal = array_fill(0, $count, null);
        if ($first !== null) {
            $defined = $this->ema(array_slice($macd, $first), $signalPeriod);
            foreach ($defined as $offset => $value) {
                $signal[$first + $offset] = $value;
            }
        }

        $histogram = array_map(
            fn ($m, $s) => $m !== null && $s !== null ? $m - $s : null,
            $macd,
            $signal,
        );

        return ['macd' => $macd, 'signal' => $signal, 'histogram' => $histogram];
    }

    /**
     * VWAP, per handelsdag (Eastern Time) opnieuw beginnend, op typical price (H+L+C)/3.
     *
     * @param  list<Candle>  $candles
     * @return list<?float>
     */
    public function vwap(array $candles): array
    {
        $result = [];
        $day = null;
        $cumulativePv = $cumulativeVolume = 0.0;

        foreach ($candles as $candle) {
            $candleDay = $candle->time->setTimezone(config('trading.timezone'))->toDateString();
            if ($candleDay !== $day) {
                $day = $candleDay;
                $cumulativePv = $cumulativeVolume = 0.0;
            }

            $typical = ($candle->highCents + $candle->lowCents + $candle->closeCents) / 3 / 100;
            $cumulativePv += $typical * $candle->volume;
            $cumulativeVolume += $candle->volume;
            $result[] = $cumulativeVolume > 0 ? $cumulativePv / $cumulativeVolume : null;
        }

        return $result;
    }

    /**
     * Berekent de gevraagde indicatoren voor een candle-reeks.
     *
     * @param  list<Candle>  $candles
     * @param  array<string, mixed>  $config  bv. ['sma' => [20], 'ema' => [9, 21], 'rsi' => 14, 'macd' => [12, 26, 9], 'vwap' => true, 'volume' => true]
     * @return array<string, mixed>
     */
    public function compute(array $candles, array $config): array
    {
        $closes = array_map(fn (Candle $c) => $c->closeCents / 100, $candles);
        $result = [];

        foreach ((array) ($config['sma'] ?? []) as $period) {
            $result["sma{$period}"] = $this->sma($closes, (int) $period);
        }
        foreach ((array) ($config['ema'] ?? []) as $period) {
            $result["ema{$period}"] = $this->ema($closes, (int) $period);
        }
        if (! empty($config['rsi'])) {
            $result['rsi'] = $this->rsi($closes, (int) $config['rsi']);
        }
        if (! empty($config['macd'])) {
            [$fast, $slow, $signal] = array_pad(array_map('intval', (array) $config['macd']), 3, 0);
            $result['macd'] = $this->macd($closes, $fast ?: 12, $slow ?: 26, $signal ?: 9);
        }
        if (! empty($config['vwap'])) {
            $result['vwap'] = $this->vwap($candles);
        }
        if (! empty($config['volume'])) {
            $result['volume'] = array_map(fn (Candle $c) => $c->volume, $candles);
        }

        return $result;
    }

    private function rsiValue(float $avgGain, float $avgLoss): float
    {
        return $avgLoss == 0.0 ? 100.0 : 100 - 100 / (1 + $avgGain / $avgLoss);
    }
}
