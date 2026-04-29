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
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        $leads = $this->leadRepo->getAllForEmpresa($empresa, request()->only('is_nearby', 'min_score', 'cidade'));

        return view('leads.index', compact('leads', 'empresa'));
    }
}
