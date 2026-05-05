<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        return view('dashboard.index', compact('empresa'));
    }
}
