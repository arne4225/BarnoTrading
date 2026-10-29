<?php

namespace App\Http\Requests;

use App\Enums\Timeframe;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tf' => ['nullable', Rule::enum(Timeframe::class)],
            'sma' => ['nullable', 'regex:/^\d{1,3}(,\d{1,3}){0,3}$/'],
            'ema' => ['nullable', 'regex:/^\d{1,3}(,\d{1,3}){0,3}$/'],
            'rsi' => ['nullable', 'integer', 'min:2', 'max:200'],
            'macd' => ['nullable', 'regex:/^\d{1,3},\d{1,3},\d{1,3}$/'],
            'vwap' => ['nullable', 'boolean'],
            'volume' => ['nullable', 'boolean'],
        ];
    }

    public function timeframe(): Timeframe
    {
        return Timeframe::tryFrom((string) $this->query('tf')) ?? Timeframe::FiveMinutes;
    }

    /**
     * @return array<string, mixed>
     */
    public function indicatorConfig(): array
    {
        $periods = fn (?string $value): array => array_values(array_filter(
            array_map('intval', explode(',', (string) $value)),
            fn (int $p) => $p >= 2 && $p <= 500,
        ));

        return array_filter([
            'sma' => $periods($this->query('sma')),
            'ema' => $periods($this->query('ema')),
            'rsi' => $this->integer('rsi') ?: null,
            'macd' => $this->filled('macd') ? array_map('intval', explode(',', $this->query('macd'))) : null,
            'vwap' => $this->boolean('vwap'),
            'volume' => $this->boolean('volume'),
        ]);
    }
}
