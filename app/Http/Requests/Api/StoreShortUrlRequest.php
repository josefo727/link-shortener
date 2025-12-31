<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

final class StoreShortUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'original_url' => ['required', 'url', 'max:2048'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'original_url.required' => 'La URL es requerida.',
            'original_url.url' => 'La URL proporcionada no es válida.',
            'original_url.max' => 'La URL no puede exceder 2048 caracteres.',
            'title.max' => 'El título no puede exceder 255 caracteres.',
            'expires_at.date' => 'La fecha de expiración no es válida.',
        ];
    }
}
