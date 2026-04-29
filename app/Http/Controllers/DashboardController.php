<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Repositories\LeadRepository;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private LeadRepository $leadRepo,
    ) {}

    public function index(): View
    {
        $empresa = auth()->user()->empresa()->firstOrCreate([]);

        $stats = $this->leadRepo->getStats($empresa);
        $conversasAtivas = $empresa->conversations()->where('status', 'active')->count();
        $totalRespostas = $empresa->conversations()
            ->whereHas('messages', fn ($q) => $q->where('sender', 'user'))
            ->count();

        $taxaResposta = $stats['total'] > 0
            ? round(($totalRespostas / $stats['total']) * 100, 1)
            : 0;

        return view('dashboard.index', compact('empresa', 'stats', 'conversasAtivas', 'taxaResposta'));
    }
}
