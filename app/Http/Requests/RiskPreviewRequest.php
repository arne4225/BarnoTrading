<?php

namespace App\Http\Requests;

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Services\MarketData\MarketDataService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiskPreviewRequest extends FormRequest
{
    private const PRICE = ['nullable', 'regex:/^\d+([.,]\d{1,2})?$/'];

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
            'side' => ['required', Rule::enum(OrderSide::class)],
            'is_short' => ['nullable', 'boolean'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'entry' => self::PRICE,
            'stop_loss' => self::PRICE,
            'take_profit' => self::PRICE,
            'risk_percent' => self::PRICE,
        ];
    }

    public function entryCents(): ?int
    {
        return Money::toCents($this->input('entry'));
    }

    public function stopLossCents(): ?int
    {
        return Money::toCents($this->input('stop_loss'));
    }

    public function takeProfitCents(): ?int
    {
        return Money::toCents($this->input('take_profit'));
    }

    /**
     * Risico-% in basispunten, of null om het persoonlijke standaardpercentage te gebruiken.
     */
    public function riskBps(): ?int
    {
        return Money::toCents($this->input('risk_percent'));
    }

    public function toOrderData(int $entryCents): PlaceOrderData
    {
        return new PlaceOrderData(
            symbol: $this->string('symbol')->value(),
            side: OrderSide::from($this->string('side')->value()),
            type: OrderType::Limit,
            quantity: max(1, $this->integer('quantity', 1)),
            isShort: $this->boolean('is_short'),
            limitPriceCents: $entryCents,
            takeProfitCents: $this->takeProfitCents(),
            stopLossCents: $this->stopLossCents(),
        );
    }
}
