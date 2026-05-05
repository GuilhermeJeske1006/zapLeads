<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        return view('leads.index', compact('empresa'));
    }
}
