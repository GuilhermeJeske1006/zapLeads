<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'preco' => 'required|numeric|min:0',
            'descricao' => 'nullable|string|max:2000',
            'imagem' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'ativo' => 'boolean',
            'ordem' => 'nullable|integer|min:0',
        ];
    }
}
