<?php

namespace App\Http\Controllers;

use App\Repositories\LeadRepository;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function __construct(
        private LeadRepository $leadRepo,
    ) {}

    public function index(): View
    {
        $loja = auth()->user()->lojas()->first();

        $leads = $loja
            ? $this->leadRepo->getAllForLoja($loja, request()->only('is_nearby', 'min_score', 'cidade'))
            : collect();

        return view('leads.index', compact('leads', 'loja'));
    }
}
