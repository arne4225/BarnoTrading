<?php

namespace App\Data;

use Carbon\CarbonImmutable;

/**
 * Een koers. Alle bedragen zijn gehele centen.
 */
final readonly class Quote
{
    public function __construct(
        public string $symbol,
        public int $priceCents,
        public int $bidCents,
        public int $askCents,
        public CarbonImmutable $asOf,
        public bool $isStale = false,
    ) {}

    public function delaySeconds(): int
    {
        return max(0, (int) $this->asOf->diffInSeconds(now(), true));
    }

    public function delayLabel(): string
    {
        $seconds = $this->delaySeconds();

        return match (true) {
            $seconds < 5 => 'realtime',
            $seconds < 120 => "{$seconds} sec vertraagd",
            default => intdiv($seconds, 60).' min vertraagd',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'symbol' => $this->symbol,
            'price_cents' => $this->priceCents,
            'bid_cents' => $this->bidCents,
            'ask_cents' => $this->askCents,
            'as_of' => $this->asOf->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, bool $isStale = false): self
    {
        return new self(
            $data['symbol'],
            $data['price_cents'],
            $data['bid_cents'],
            $data['ask_cents'],
            CarbonImmutable::parse($data['as_of']),
            $isStale,
        );
    }
}
