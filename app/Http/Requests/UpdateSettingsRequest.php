<?php

namespace App\Http\Requests;

use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    private const DECIMAL = ['regex:/^\d+([.,]\d{1,2})?$/'];

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
            'commission' => ['nullable', ...self::DECIMAL, 'max:1000'],
            'slippage_percent' => ['nullable', ...self::DECIMAL, 'max:10'],
            'risk_per_trade_percent' => ['required', ...self::DECIMAL],
            'max_position_percent' => ['required', 'integer', 'min:1', 'max:1000'],
            'min_risk_reward' => ['required', ...self::DECIMAL],
            'warn_without_stop_loss' => ['nullable', 'boolean'],
            'daily_loss_limit_percent' => ['nullable', ...self::DECIMAL],
            'leaderboard_visible' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.regex' => 'Gebruik een getal met maximaal twee decimalen, bijvoorbeeld 1.5.'];
    }

    /**
     * Zet formulierwaarden om naar opslagwaarden (centen, basispunten, honderdsten).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'commission_cents' => Money::toCents($this->input('commission')),
            'slippage_bps' => Money::toCents($this->input('slippage_percent')),
            'risk_per_trade_bps' => Money::toCents($this->input('risk_per_trade_percent')),
            'max_position_percent' => $this->integer('max_position_percent'),
            'min_risk_reward_hundredths' => Money::toCents($this->input('min_risk_reward')),
            'warn_without_stop_loss' => $this->boolean('warn_without_stop_loss'),
            'daily_loss_limit_bps' => Money::toCents($this->input('daily_loss_limit_percent')),
            'leaderboard_visible' => $this->boolean('leaderboard_visible'),
        ];
    }
}
