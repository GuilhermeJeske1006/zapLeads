<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CaptureLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'telefone' => 'required|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'cidade' => 'nullable|string|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefone' => preg_replace('/\D/', '', $this->telefone ?? ''),
        ]);
    }
}
