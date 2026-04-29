<?php

namespace App\Http\Controllers;

use App\Models\Loja;
use App\Repositories\LeadRepository;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private LeadRepository $leadRepo,
    ) {}

    public function index(): View
    {
        $loja = auth()->user()->lojas()->first();

        if (!$loja) {
            return view('dashboard.no-loja');
        }

        $stats = $this->leadRepo->getStats($loja);
        $conversasAtivas = $loja->conversations()->where('status', 'active')->count();
        $totalRespostas = $loja->conversations()
            ->whereHas('messages', fn ($q) => $q->where('sender', 'user'))
            ->count();

        $taxaResposta = $stats['total'] > 0
            ? round(($totalRespostas / $stats['total']) * 100, 1)
            : 0;

        return view('dashboard.index', compact('loja', 'stats', 'conversasAtivas', 'taxaResposta'));
    }
}
