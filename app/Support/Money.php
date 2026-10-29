<?php

namespace App\Support;

/**
 * Conversie tussen gebruikersinvoer/weergave in dollars en gehele centen.
 */
final class Money
{
    /**
     * Zet "12", "12.5" of "12,50" om naar centen. Geeft null bij lege of ongeldige invoer.
     */
    public static function toCents(string|int|float|null $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim((string) $input));

        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $normalized, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '0', 2, '0');
    }

    public static function format(int $cents, bool $signed = false): string
    {
        $sign = $cents < 0 ? '-' : ($signed && $cents > 0 ? '+' : '');

        return $sign.'$'.number_format(abs($cents) / 100, 2);
    }
}
