<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ProspectingController extends Controller
{
    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        return view('prospeccao.index', compact('empresa'));
    }
}
