<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Jobs\FindInternetLeadsJob;
use App\Services\Geo\GeocodingService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use Livewire\Attributes\Locked;
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

    #[Locked]
    public ?int $searchId = null;

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

        // Created before the job so polling follows this exact search, even with others running.
        $search = ProspectingSearch::create([
            'empresa_id'        => $this->empresa->id,
            'descricao_empresa' => $this->descricaoEmpresa,
            'tipo_cliente'      => $this->tipoCliente,
            'latitude'          => $this->customLat ?? (float) ($this->empresa->latitude ?? 0),
            'longitude'         => $this->customLng ?? (float) ($this->empresa->longitude ?? 0),
            'radius_km'         => $this->raioBuscaKm,
            'keywords'          => [],
            'status'            => 'queued',
        ]);

        $this->buscando = true;
        $this->buscaFeita = false;
        $this->resultados = [];
        $this->searchId = $search->id;

        FindInternetLeadsJob::dispatch(
            $search->id,
            60,
            $this->customLat,
            $this->customLng,
            $this->customLocationLabel ?: $localBusca,
        );
    }

    public function pollSearch(): void
    {
        if (!$this->buscando || !$this->searchId) {
            return;
        }

        $search = ProspectingSearch::where('empresa_id', $this->empresa->id)->find($this->searchId);

        if ($search && in_array($search->status, ['queued', 'running'], true)) {
            return;
        }

        $this->buscando = false;

        if (!$search || $search->status === 'failed') {
            $this->dispatch('toast', type: 'error', message: $search?->error ?: 'Erro ao buscar leads na internet.');
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

    public function enviarMensagemIA(int $leadId): void
    {
        $lead = $this->empresa->leads()->findOrFail($leadId);
        $outreach = app(OutreachService::class);

        try {
            $outreach->send($outreach->prepare($lead));
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

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
        $search = $this->searchId
            ? ProspectingSearch::where('empresa_id', $this->empresa->id)->find($this->searchId)
            : null;

        return view('livewire.leads.internet-prospector', compact('search'));
    }
}
