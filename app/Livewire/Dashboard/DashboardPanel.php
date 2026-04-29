<?php

namespace App\Livewire\Dashboard;

use App\Models\Loja;
use App\Repositories\LeadRepository;
use App\Services\AIService;
use Livewire\Component;

class DashboardPanel extends Component
{
    public Loja $loja;
    public array $stats = [];
    public int $conversasAtivas = 0;
    public float $taxaResposta = 0;
    public array $aiInsights = [];
    public bool $loadingInsights = false;

    public function mount(LeadRepository $leadRepo): void
    {
        $this->stats = $leadRepo->getStats($this->loja);
        $this->conversasAtivas = $this->loja->conversations()->where('status', 'active')->count();
        $totalRespostas = $this->loja->conversations()
            ->whereHas('messages', fn ($q) => $q->where('sender', 'user'))
            ->count();
        $this->taxaResposta = $this->stats['total'] > 0
            ? round(($totalRespostas / $this->stats['total']) * 100, 1)
            : 0;
    }

    public function loadAIInsights(AIService $ai): void
    {
        $this->loadingInsights = true;
        try {
            $leads = $this->loja->leads()->limit(50)->get()->toArray();
            $this->aiInsights = $ai->sugerirCampanha($leads, 'análise geral do negócio');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AIService sugerirCampanha failed', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Limite da API atingido. Tente novamente em alguns minutos.');
        } finally {
            $this->loadingInsights = false;
        }
    }

    public function render()
    {
        $recentLeads = $this->loja->leads()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $recentConversations = $this->loja->conversations()
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get();

        return view('livewire.dashboard.dashboard-panel', compact('recentLeads', 'recentConversations'));
    }
}
