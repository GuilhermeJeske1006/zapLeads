<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Models\Loja;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeadsTable extends Component
{
    use WithPagination;

    public Loja $loja;

    public bool $showModal = false;
    public ?array $modalLead = null;

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'fonte')]
    public string $filterSource = '';

    #[Url(as: 'proximo')]
    public bool $filterNearby = false;

    #[Url(as: 'score')]
    public string $filterMinScore = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterSource(): void
    {
        $this->resetPage();
    }

    public function updatingFilterNearby(): void
    {
        $this->resetPage();
    }

    public function updatingFilterMinScore(): void
    {
        $this->resetPage();
    }

    public function limparFiltros(): void
    {
        $this->search = '';
        $this->filterStatus = '';
        $this->filterSource = '';
        $this->filterNearby = false;
        $this->filterMinScore = '';
        $this->resetPage();
    }

    public function alterarStatus(int $leadId, string $status): void
    {
        if (!array_key_exists($status, Lead::STATUSES)) {
            return;
        }

        $lead = $this->loja->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: 'Status atualizado.');
    }

    public function deleteLead(int $leadId): void
    {
        $lead = $this->loja->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->delete();
        $this->dispatch('toast', type: 'success', message: 'Lead excluído.');
    }

    public function deleteAllLeads(): void
    {
        $count = $this->loja->leads()->count();
        $this->loja->leads()->delete();
        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: "{$count} leads excluídos.");
    }

    public function abrirModal(int $leadId): void
    {
        $lead = $this->loja->leads()->find($leadId);
        if (!$lead) {
            return;
        }
        $this->modalLead = $lead->toArray();
        $this->showModal  = true;
    }

    public function fecharModal(): void
    {
        $this->showModal  = false;
        $this->modalLead  = null;
    }

    public function render()
    {
        $sources = $this->loja->leads()->whereNotNull('source')->distinct()->pluck('source')->sort()->values();

        $leads = $this->loja->leads()
            ->when($this->search, fn ($q) => $q->where('nome', 'like', '%' . $this->search . '%'))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSource, fn ($q) => $q->where('source', $this->filterSource))
            ->when($this->filterNearby, fn ($q) => $q->where('is_nearby', true))
            ->when($this->filterMinScore !== '', fn ($q) => $q->where('lead_score', '>=', (int) $this->filterMinScore))
            ->orderByDesc('lead_score')
            ->paginate(20);

        $hasFilters = $this->search || $this->filterStatus || $this->filterSource || $this->filterNearby || $this->filterMinScore !== '';

        return view('livewire.leads.leads-table', compact('leads', 'sources', 'hasFilters'));
    }
}
