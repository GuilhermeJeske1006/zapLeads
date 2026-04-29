<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);
        return view('campaigns.index', compact('empresa'));
    }
}
