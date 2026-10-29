<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTradeRequest extends FormRequest
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
            'entry_reason' => ['nullable', 'string', 'max:2000'],
            'note' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:300'],
            'self_rating' => ['nullable', 'integer', 'between:1,5'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'entry_reason' => $this->input('entry_reason') ?: null,
            'note' => $this->input('note') ?: null,
            'tags' => $this->tagList() ?: null,
            'self_rating' => $this->filled('self_rating') ? $this->integer('self_rating') : null,
        ];
    }

    /**
     * Komma-gescheiden tags als unieke, kleine letters; maximaal 8 van 24 tekens.
     *
     * @return list<string>
     */
    public function tagList(): array
    {
        return collect(explode(',', (string) $this->input('tags')))
            ->map(fn (string $tag) => mb_substr(mb_strtolower(trim($tag)), 0, 24))
            ->filter()->unique()->take(8)->values()->all();
    }
}
