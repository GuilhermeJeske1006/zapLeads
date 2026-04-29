<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(): View
    {
        $loja = auth()->user()->lojas()->first();
        return view('campaigns.index', compact('loja'));
    }
}
