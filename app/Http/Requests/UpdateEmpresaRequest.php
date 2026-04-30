<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'nullable|string|max:255',
            'whatsapp' => 'required|string|max:20',
            'endereco' => 'required|string|max:500',
            'cidade' => 'required|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'raio_atendimento' => 'nullable|integer|min:1|max:500',
            'slug' => 'nullable|string|max:100|unique:empresas,slug',
            'logo' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'ai_persona' => 'nullable|string|max:2000',
        ];
    }
}

