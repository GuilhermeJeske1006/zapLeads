<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ProdutosController extends Controller
{
    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        return view('produtos.index', compact('empresa'));
    }
}

