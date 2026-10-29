<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetAccountRequest extends FormRequest
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
            'starting_cash' => ['required', 'integer', 'min:1000', 'max:100000000'],
            'confirm' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.accepted' => 'Bevestig dat je alles wilt wissen.',
            'starting_cash.min' => 'Het startkapitaal moet minimaal $1.000 zijn.',
        ];
    }
}
