<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLojaRequest;
use App\Models\Loja;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LojaController extends Controller
{
    use AuthorizesRequests;
    public function index(): View
    {
        $lojas = auth()->user()->lojas()->latest()->paginate(10);
        return view('lojas.index', compact('lojas'));
    }

    public function create(): View
    {
        return view('lojas.create');
    }

    public function store(StoreLojaRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['slug'] = $data['slug'] ?? Str::slug($data['nome']) . '-' . Str::random(4);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('lojas', 'public');
        }

        Loja::create($data);

        return redirect()->route('lojas.index')->with('success', __('messages.loja_created'));
    }

    public function edit(Loja $loja): View
    {
        $this->authorize('update', $loja);
        return view('lojas.edit', compact('loja'));
    }

    public function update(StoreLojaRequest $request, Loja $loja): RedirectResponse
    {
        $this->authorize('update', $loja);
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('lojas', 'public');
        }

        $loja->update($data);

        return redirect()->route('lojas.index')->with('success', __('messages.loja_updated'));
    }

    public function destroy(Loja $loja): RedirectResponse
    {
        $this->authorize('delete', $loja);
        $loja->delete();
        return redirect()->route('lojas.index')->with('success', __('messages.loja_deleted'));
    }
}
