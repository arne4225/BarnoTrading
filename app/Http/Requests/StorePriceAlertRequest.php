<?php

namespace App\Http\Requests;

use App\Services\MarketData\MarketDataService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['symbol' => MarketDataService::normalizeSymbol((string) $this->input('symbol'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'regex:/^[A-Z]{1,5}(\.[A-Z])?$/'],
            'price' => ['required', 'regex:/^\d+([.,]\d{1,2})?$/'],
        ];
    }

    public function priceCents(): int
    {
        return Money::toCents($this->input('price')) ?? 0;
    }
}
