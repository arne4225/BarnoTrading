<?php

namespace App\Http\Requests;

use App\Data\PlaceOrderData;
use App\Enums\OrderSide;
use App\Enums\OrderType;
use App\Services\MarketData\MarketDataService;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
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
            'type' => ['required', Rule::enum(OrderType::class)],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_short' => ['nullable', 'boolean'],
            'limit_price' => self::PRICE,
            'stop_price' => self::PRICE,
            'trail_amount' => self::PRICE,
            'trail_percent' => self::PRICE,
            'take_profit' => self::PRICE,
            'stop_loss' => self::PRICE,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.regex' => 'Gebruik een bedrag met maximaal twee decimalen, bijvoorbeeld 123.45.',
        ];
    }

    public function toData(): PlaceOrderData
    {
        $type = OrderType::from($this->string('type')->value());

        return new PlaceOrderData(
            symbol: $this->string('symbol')->value(),
            side: OrderSide::from($this->string('side')->value()),
            type: $type,
            quantity: $this->integer('quantity'),
            isShort: $this->boolean('is_short'),
            limitPriceCents: $type->needsLimitPrice() ? Money::toCents($this->input('limit_price')) : null,
            stopPriceCents: $type->needsStopPrice() ? Money::toCents($this->input('stop_price')) : null,
            trailAmountCents: $type === OrderType::TrailingStop ? Money::toCents($this->input('trail_amount')) : null,
            // Percentage met twee decimalen in basispunten: 2,5% => 250.
            trailPercentBps: $type === OrderType::TrailingStop ? Money::toCents($this->input('trail_percent')) : null,
            takeProfitCents: Money::toCents($this->input('take_profit')),
            stopLossCents: Money::toCents($this->input('stop_loss')),
        );
    }
}
