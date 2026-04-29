<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Models\Conversation;
use App\Models\Message;
use App\Jobs\FindInternetLeadsJob;
use App\Jobs\SendWhatsAppMessageJob;
use App\Services\AIService;
use Livewire\Component;

class InternetProspector extends Component
{
    public Empresa $empresa;

    public string $descricaoEmpresa = '';
    public string $tipoCliente = '';
    public string $endereco = '';
    public string $cidade = '';
    public float $raioBuscaKm = 5;

    public bool $buscando = false;
    public bool $buscaFeita = false;
    public ?int $searchId = null;
    public string $dispatchedAt = '';

    /** @var array<int, array<string, mixed>> */
    public array $resultados = [];

    public bool $showModal = false;
    public ?array $modalLead = null;

    public function mount(): void
    {
        $this->descricaoEmpresa = (string) ($this->empresa->descricao_empresa ?? '');
        $this->tipoCliente = (string) ($this->empresa->tipo_cliente_alvo ?? '');
        $this->endereco = (string) ($this->empresa->endereco ?? '');
        $this->cidade = (string) ($this->empresa->cidade ?? '');
        $this->raioBuscaKm = (float) ($this->empresa->raio_atendimento ?? 5);
    }

    public function buscar(): void
    {
        $this->validate([
            'descricaoEmpresa' => 'required|min:10',
            'tipoCliente' => 'required|min:5',
            'endereco' => 'required|min:5',
            'cidade' => 'required|min:2',
            'raioBuscaKm' => 'required|numeric|min:1|max:50',
        ], [
            'descricaoEmpresa.required' => 'Descreva sua empresa.',
            'tipoCliente.required' => 'Informe o tipo de cliente desejado.',
            'endereco.required' => 'Informe o endereço da empresa.',
            'cidade.required' => 'Informe a cidade.',
            'raioBuscaKm.required' => 'Informe o raio de busca.',
        ]);

        $this->empresa->update([
            'descricao_empresa' => $this->descricaoEmpresa,
            'tipo_cliente_alvo' => $this->tipoCliente,
            'endereco' => $this->endereco,
            'cidade' => $this->cidade,
            'raio_atendimento' => $this->raioBuscaKm,
        ]);

        $this->buscando = true;
        $this->buscaFeita = false;
        $this->resultados = [];
        $this->searchId = null;
        $this->dispatchedAt = now()->utc()->toDateTimeString();

        FindInternetLeadsJob::dispatch(
            $this->empresa->id,
            $this->descricaoEmpresa,
            $this->tipoCliente,
            $this->raioBuscaKm,
        );
    }

    public function pollSearch(): void
    {
        if (!$this->buscando || !$this->dispatchedAt) {
            return;
        }

        $search = ProspectingSearch::where('empresa_id', $this->empresa->id)
            ->where('created_at', '>=', $this->dispatchedAt)
            ->orderByDesc('created_at')
            ->first();

        if (!$search) {
            return;
        }

        if ($search->status === 'running') {
            $this->searchId = $search->id;
            return;
        }

        $this->searchId = $search->id;
        $this->buscando = false;

        if ($search->status === 'failed') {
            $this->dispatch('toast', type: 'error', message: $search->error ?: 'Erro ao buscar leads na internet.');
            return;
        }

        $this->resultados = Lead::where('empresa_id', $this->empresa->id)
            ->where('prospecting_search_id', $search->id)
            ->orderByDesc('lead_score')
            ->limit(80)
            ->get()
            ->map(fn ($l) => $l->toArray())
            ->values()
            ->all();

        $this->buscaFeita = true;
        $this->dispatch('internet-leads-updated', leads: $this->resultados, empresa: $this->empresa->fresh());
    }

    public function enviarMensagemIA(int $leadId, AIService $ai): void
    {
        $lead = $this->empresa->leads()->findOrFail($leadId);

        if (!trim((string) $lead->telefone)) {
            $this->dispatch('toast', type: 'error', message: 'Este lead não tem telefone disponível.');
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

    public function verNaTabelaDeLeads(): void
    {
        $this->dispatch('leads-table-filter', source: 'internet', prospectingSearchId: $this->searchId);
        $this->dispatch('scroll-to-leads-table');
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

    public function alterarStatus(int $leadId, string $status): void
    {
        $allowed = array_keys(\App\Models\Lead::STATUSES);
        if (!in_array($status, $allowed, true)) {
            return;
        }

        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->update(['status' => $status]);

        foreach ($this->resultados as &$r) {
            if ((int) $r['id'] === $leadId) {
                $r['status'] = $status;
                break;
            }
        }
        unset($r);

        $this->dispatch('toast', type: 'success', message: 'Status atualizado.');
    }

    public function render()
    {
        $search = $this->searchId ? ProspectingSearch::with('leads')->find($this->searchId) : null;
        return view('livewire.leads.internet-prospector', compact('search'));
    }
}
