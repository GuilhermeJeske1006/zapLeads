<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Empresa;
use App\Jobs\SendWhatsAppMessageJob;
use App\Services\AIService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LeadsTable extends Component
{
    use WithPagination;

    public Empresa $empresa;

    public bool $showModal = false;
    public ?array $modalLead = null;

    public bool $showAddModal = false;
    public string $addNome = '';
    public string $addTelefone = '';
    public string $addStatus = 'novo';
    public string $addCidade = '';
    public string $addEndereco = '';
    public string $addWebsite = '';

    #[Url(as: 'busca')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $filterStatus = '';

    #[Url(as: 'fonte')]
    public string $filterSource = '';

    #[Url(as: 'ps')]
    public string $filterProspectingSearchId = '';

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

    public function updatingFilterProspectingSearchId(): void
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
        $this->filterProspectingSearchId = '';
        $this->filterNearby = false;
        $this->filterMinScore = '';
        $this->resetPage();
    }

    #[On('leads-table-filter')]
    public function aplicarFiltro(string $source = '', ?int $prospectingSearchId = null): void
    {
        $this->filterSource = $source;
        $this->filterProspectingSearchId = $prospectingSearchId ? (string) $prospectingSearchId : '';
        $this->resetPage();
    }

    public function alterarStatus(int $leadId, string $status): void
    {
        if (!array_key_exists($status, Lead::STATUSES)) {
            return;
        }

        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->update(['status' => $status]);
        $this->dispatch('toast', type: 'success', message: 'Status atualizado.');
    }

    public function deleteLead(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->delete();
        $this->dispatch('toast', type: 'success', message: 'Lead excluído.');
    }

    public function deleteAllLeads(): void
    {
        $count = $this->empresa->leads()->count();
        $this->empresa->leads()->delete();
        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: "{$count} leads excluídos.");
    }

    public function enviarMensagemIA(int $leadId, AIService $ai): void
    {
        $lead = $this->empresa->leads()->findOrFail($leadId);

        if (!trim((string) $lead->telefone)) {
            $this->dispatch('toast', type: 'error', message: 'Este lead não tem telefone disponível.');
            return;
        }

        if (method_exists($lead, 'isOptedOut') && $lead->isOptedOut()) {
            $this->dispatch('toast', type: 'error', message: 'Este lead optou por não receber mensagens.');
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'telefone' => $lead->telefone],
            ['lead_id' => $lead->id, 'nome_contato' => $lead->nome, 'status' => 'active']
        );

        $text = $ai->gerarPrimeiraMensagemProspeccao($this->empresa, $lead);
        if (!trim($text)) {
            $this->dispatch('toast', type: 'error', message: 'Não foi possível gerar a mensagem. Tente novamente.');
            return;
        }

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'user',
            'message' => $text,
            'type' => 'text',
            'status' => 'sending',
            'ai_generated' => true,
        ]);

        SendWhatsAppMessageJob::dispatch($msg);

        $conversation->update([
            'last_message' => $text,
            'last_message_at' => now(),
            'status' => 'active',
        ]);

        $this->dispatch('toast', type: 'success', message: 'Mensagem enviada pela IA (fila).');
    }

    public function abrirModal(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
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

    public function abrirAddModal(): void
    {
        $this->addNome     = '';
        $this->addTelefone = '';
        $this->addStatus   = 'novo';
        $this->addCidade   = '';
        $this->addEndereco = '';
        $this->addWebsite  = '';
        $this->resetValidation();
        $this->showAddModal = true;
    }

    public function fecharAddModal(): void
    {
        $this->showAddModal = false;
    }

    public function salvarLead(): void
    {
        if ($this->addWebsite && !preg_match('/^https?:\/\//i', $this->addWebsite)) {
            $this->addWebsite = 'https://' . $this->addWebsite;
        }

        $this->validate([
            'addNome'     => 'required|string|max:255',
            'addTelefone' => 'required|string|max:20',
            'addStatus'   => 'required|in:' . implode(',', array_keys(Lead::STATUSES)),
            'addCidade'   => 'nullable|string|max:255',
            'addEndereco' => 'nullable|string|max:255',
            'addWebsite'  => 'nullable|url|max:255',
        ], [
            'addNome.required'     => 'Nome obrigatório.',
            'addTelefone.required' => 'Telefone obrigatório.',
            'addWebsite.url'       => 'URL inválida (ex: https://site.com).',
        ]);

        Lead::create([
            'empresa_id' => $this->empresa->id,
            'nome'       => $this->addNome,
            'telefone'   => preg_replace('/\D/', '', $this->addTelefone),
            'status'     => $this->addStatus,
            'cidade'     => $this->addCidade ?: null,
            'endereco'   => $this->addEndereco ?: null,
            'website'    => $this->addWebsite ?: null,
            'source'     => 'manual',
        ]);

        $this->showAddModal = false;
        $this->dispatch('toast', type: 'success', message: 'Lead adicionado.');
    }

    public function render()
    {
        $sources = $this->empresa->leads()->whereNotNull('source')->distinct()->pluck('source')->sort()->values();

        $leads = $this->empresa->leads()
            ->when($this->search, fn ($q) => $q->where('nome', 'like', '%' . $this->search . '%'))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSource, fn ($q) => $q->where('source', $this->filterSource))
            ->when($this->filterProspectingSearchId !== '', fn ($q) => $q->where('prospecting_search_id', (int) $this->filterProspectingSearchId))
            ->when($this->filterNearby, fn ($q) => $q->where('is_nearby', true))
            ->when($this->filterMinScore !== '', fn ($q) => $q->where('lead_score', '>=', (int) $this->filterMinScore))
            ->orderByDesc('lead_score')
            ->paginate(20);

        $hasFilters = $this->search || $this->filterStatus || $this->filterSource || $this->filterProspectingSearchId !== '' || $this->filterNearby || $this->filterMinScore !== '';

        return view('livewire.leads.leads-table', compact('leads', 'sources', 'hasFilters'));
    }
}
