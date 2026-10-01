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
use App\Services\Geo\GeocodingService;
use Livewire\Attributes\On;
use Livewire\Component;

class InternetProspector extends Component
{
    public Empresa $empresa;

    public string $descricaoEmpresa = '';
    public string $tipoCliente = '';
    public string $endereco = '';
    public string $cidade = '';
    public string $localBusca = '';
    public float $raioBuscaKm = 5;

    public bool $buscando = false;
    public bool $buscaFeita = false;
    public ?int $searchId = null;
    public string $dispatchedAt = '';

    public ?float $customLat = null;
    public ?float $customLng = null;
    public string $customLocationLabel = '';

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
        $this->localBusca = '';
        $this->raioBuscaKm = (float) ($this->empresa->raio_atendimento ?? 5);
    }

    public function setCustomLocation(float $lat, float $lng, string $label = ''): void
    {
        $this->customLat = $lat;
        $this->customLng = $lng;
        $this->customLocationLabel = mb_substr($label ?: number_format($lat, 5) . ', ' . number_format($lng, 5), 0, 150);
        $this->localBusca = $this->customLocationLabel;
    }

    #[On('internet-prospector:set-custom-location')]
    public function setCustomLocationFromEvent($lat = null, $lng = null, string $label = ''): void
    {
        if ($lat === null || $lng === null) {
            return;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        if (!$lat || !$lng) {
            return;
        }

        $this->setCustomLocation($lat, $lng, $label);
    }

    public function resetLocation(): void
    {
        $this->customLat = null;
        $this->customLng = null;
        $this->customLocationLabel = '';
        $this->localBusca = '';
        $this->dispatch('location-reset');
    }

    public function buscar(): void
    {
        $this->validate([
            'descricaoEmpresa' => 'required|min:10',
            'tipoCliente' => 'required|min:5',
            'endereco' => 'required|min:5',
            'cidade' => 'required|min:2',
            'localBusca' => 'nullable|string|min:2|max:150',
            'raioBuscaKm' => 'required|numeric|min:1|max:50',
        ], [
            'descricaoEmpresa.required' => 'Descreva sua empresa.',
            'tipoCliente.required' => 'Informe o tipo de cliente desejado.',
            'endereco.required' => __('messages.address_required'),
            'cidade.required' => 'Informe a cidade.',
            'raioBuscaKm.required' => 'Informe o raio de busca.',
        ]);

        $localBusca = trim($this->localBusca);
        if ($localBusca !== '') {
            $hasSelectedMapPoint = $this->customLat !== null
                && $this->customLng !== null
                && $localBusca === trim($this->customLocationLabel);

            if (!$hasSelectedMapPoint) {
                $geo = app(GeocodingService::class);
                $geoQuery = $localBusca;
                $cityHint = trim($this->cidade);
                if ($cityHint !== '' && !str_contains(mb_strtolower($geoQuery), mb_strtolower($cityHint))) {
                    $geoQuery .= ' ' . $cityHint;
                }

                $hit = $geo->geocode($geoQuery);
                if (!$hit) {
                    $this->dispatch('toast', type: 'error', message: 'Não foi possível localizar o local da busca. Verifique cidade/UF e tente novamente.');
                    return;
                }

                $this->customLat = (float) $hit['lat'];
                $this->customLng = (float) $hit['lng'];
                $this->customLocationLabel = mb_substr((string) ($hit['display_name'] ?? $localBusca), 0, 150);
                $this->localBusca = $this->customLocationLabel;
            }
        } else {
            $this->customLat = null;
            $this->customLng = null;
            $this->customLocationLabel = '';
        }

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
            60,
            $this->customLat,
            $this->customLng,
            $this->customLocationLabel ?: $localBusca,
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
        $this->dispatch('internet-leads-updated', leads: $this->resultados, empresa: $this->empresa->fresh(), searchCenter: [
            'lat' => $search->latitude,
            'lng' => $search->longitude,
        ]);
    }

    public function enviarMensagemIA(int $leadId, AIService $ai): void
    {
        $lead = $this->empresa->leads()->findOrFail($leadId);

        if (!trim((string) $lead->telefone)) {
            $this->dispatch('toast', type: 'error', message: __('messages.lead_no_phone'));
            return;
        }

        if (!$lead->telefone_e164) {
            $this->dispatch('toast', type: 'error', message: __('messages.lead_invalid_phone'));
            return;
        }

        if ($lead->isOptedOut()) {
            $this->dispatch('toast', type: 'error', message: __('messages.lead_opted_out'));
            return;
        }

        $channel = $this->empresa->defaultChannel();
        if (!$channel) {
            $this->dispatch('toast', type: 'error', message: __('messages.whatsapp_channel_required'));
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'telefone_e164' => $lead->telefone_e164],
            ['telefone' => $lead->telefone, 'lead_id' => $lead->id, 'nome_contato' => $lead->nome, 'status' => 'active', 'whatsapp_channel_id' => $channel->id]
        );

        if (!$conversation->whatsapp_channel_id) {
            $conversation->update(['whatsapp_channel_id' => $channel->id]);
        }

        $text = $ai->gerarPrimeiraMensagemProspeccao($this->empresa, $lead);
        if (!trim($text)) {
            $this->dispatch('toast', type: 'error', message: __('messages.message_generation_failed'));
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

        $this->dispatch('toast', type: 'success', message: __('messages.message_sent_ai'));
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
        $this->dispatch('toast', type: 'success', message: __('messages.status_updated'));

        foreach ($this->resultados as &$r) {
            if ((int) $r['id'] === $leadId) {
                $r['status'] = $status;
                break;
            }
        }
        unset($r);
    }

    public function render()
    {
        $search = $this->searchId ? ProspectingSearch::with('leads')->find($this->searchId) : null;
        return view('livewire.leads.internet-prospector', compact('search'));
    }
}
