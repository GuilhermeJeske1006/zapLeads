<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmpresaRequest;
use App\Models\Empresa;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    use AuthorizesRequests;

    public function edit(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);
        return view('empresa.edit', compact('empresa'));
    }

    public function update(UpdateEmpresaRequest $request): RedirectResponse
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);
        $data = $request->validated();

        // The slug is the public catalog URL: forms that don't send it (AI persona) must not change it.
        $data['slug'] = $data['slug'] ?? $empresa->slug ?? Str::slug($data['nome'] ?? $empresa->nome ?? 'empresa') . '-' . Str::random(4);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('empresas', 'public');
        }

        $empresa->update($data);

        return redirect()->route('empresa.edit')->with('success', __('messages.empresa_updated'));
    }
}

