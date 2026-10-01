<?php

namespace App\Livewire\Leads;

use App\Models\Lead;
use App\Models\Empresa;
use App\Models\WhatsAppChannel;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\GeoService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use App\Services\Scoring\LeadScoringService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
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

    public bool $showSelectChannelModal = false;
    public ?int $pendingLeadId = null;
    public array $channels = [];
    public ?int $selectedChannelId = null;

    public bool $showAddModal = false;
    public string $addNome = '';
    public string $addTelefone = '';
    public string $addStatus = 'novo';
    public string $addCidade = '';
    public string $addEndereco = '';
    public string $addWebsite = '';

    public bool $geoFilterEnabled = false;
    public ?float $geoLat = null;
    public ?float $geoLng = null;
    public float $geoRadiusKm = 5;
    public string $geoLabel = '';

    public function mount(): void
    {
        $this->geoRadiusKm = (float) ($this->empresa->raio_atendimento ?? 5);
    }

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

    public function updatingGeoFilterEnabled(): void
    {
        $this->resetPage();
    }

    public function updatedGeoFilterEnabled(bool $value): void
    {
        if (!$value) {
            $this->geoLat = null;
            $this->geoLng = null;
            $this->geoLabel = '';
            $this->dispatch('leads-geo-reset');
            return;
        }

        if ($this->geoLat === null && $this->geoLng === null && $this->empresa->latitude && $this->empresa->longitude) {
            $this->geoLat = round((float) $this->empresa->latitude, 7);
            $this->geoLng = round((float) $this->empresa->longitude, 7);
            $this->geoLabel = 'Sua empresa';
        }

        $this->dispatch('leads-geo-open');
    }

    public function updatingGeoRadiusKm(): void
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
        $this->geoFilterEnabled = false;
        $this->geoLat = null;
        $this->geoLng = null;
        $this->geoRadiusKm = (float) ($this->empresa->raio_atendimento ?? 5);
        $this->geoLabel = '';
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
        $this->dispatch('toast', type: 'success', message: __('messages.status_updated'));
    }

    public function deleteLead(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        $lead->delete();
        $this->dispatch('toast', type: 'success', message: __('messages.lead_deleted'));
    }

    public function deleteAllLeads(): void
    {
        $count = $this->empresa->leads()->count();
        $this->empresa->leads()->delete();
        $this->resetPage();
        $this->dispatch('toast', type: 'success', message: __('messages.leads_deleted', ['count' => $count]));
    }

    public function gerarAbordagem(int $leadId): void
    {
        $lead = $this->empresa->leads()->findOrFail($leadId);

        // Checked before the channel picker, so nobody picks a channel for an unreachable lead.
        try {
            app(OutreachService::class)->assertReachable($lead);
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

        $channels = $this->empresa->whatsappChannels()->where('ativo', true)->get();

        if ($channels->count() > 1) {
            $this->pendingLeadId = $leadId;
            $this->channels = $channels->map(fn ($c) => ['id' => $c->id, 'nome' => $c->nome, 'numero' => $c->numero])->toArray();
            $this->selectedChannelId = $channels->firstWhere('is_default', true)?->id ?? $channels->first()->id;
            $this->showSelectChannelModal = true;
            return;
        }

        // Without a channel the draft can still be sent from the user's own WhatsApp.
        $this->pedirAbordagem($lead, $channels->first());
    }

    public function confirmarCanal(): void
    {
        if (!$this->pendingLeadId || !$this->selectedChannelId) {
            return;
        }

        $channel = $this->empresa->whatsappChannels()->find($this->selectedChannelId);
        $lead = $this->empresa->leads()->find($this->pendingLeadId);
        if (!$channel || !$lead) {
            return;
        }

        $this->fecharSelectChannelModal();
        $this->pedirAbordagem($lead, $channel);
    }

    public function fecharSelectChannelModal(): void
    {
        $this->showSelectChannelModal = false;
        $this->pendingLeadId = null;
        $this->channels = [];
    }

    /** Looks for the lead's WhatsApp, decision maker and context in the background. */
    public function buscarContatos(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        app(LeadEnrichmentService::class)->queue($lead);
        $this->fecharModal();
        $this->dispatch('toast', type: 'success', message: __('messages.enrichment_queued', ['nome' => $lead->nome]));
    }

    /** Re-evaluates the prospect leads with the current sales profile, in the background. */
    public function recalcularScores(): void
    {
        $count = app(LeadScoringService::class)->queueRescore($this->empresa);

        $this->dispatch('toast', ...match (true) {
            $count === null => ['type' => 'info', 'message' => __('messages.rescore_running')],
            $count === 0    => ['type' => 'info', 'message' => __('messages.rescore_nothing')],
            default         => ['type' => 'success', 'message' => __('messages.rescore_queued', ['count' => $count])],
        });
    }

    /** "Ele respondeu": the lead answered a message the system didn't see. */
    public function registrarResposta(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }

        app(OutreachService::class)->registerReply($lead);
        $this->fecharModal();
        $this->dispatch('toast', type: 'success', message: __('messages.reply_registered'));
    }

    private function pedirAbordagem(Lead $lead, ?WhatsAppChannel $channel): void
    {
        try {
            app(OutreachService::class)->request($lead, $channel);
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

        $this->dispatch('outreach-requested');
        $this->dispatch('toast', type: 'success', message: __('messages.outreach_requested', ['nome' => $lead->nome]));
    }

    public function abrirModal(int $leadId): void
    {
        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead) {
            return;
        }
        $this->modalLead = $lead->toArray() + [
            'aguardando_resposta' => $lead->outreachAttempts()->whereNull('responded_at')->exists(),
            'contatos'            => $lead->contacts()
                ->orderByDesc('is_primary')
                ->orderByDesc('confianca')
                ->get(['tipo', 'valor', 'confianca', 'origem', 'evidencia', 'is_primary', 'provavel_decisor'])
                ->toArray(),
        ];
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
            'addNome.required'     => __('messages.name_required'),
            'addTelefone.required' => __('messages.phone_required'),
            'addWebsite.url'       => __('messages.url_invalid'),
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
        $this->dispatch('toast', type: 'success', message: __('messages.lead_added'));
    }

    public function setGeoCenter(float $lat, float $lng, string $label = ''): void
    {
        validator(
            ['lat' => $lat, 'lng' => $lng],
            [
                'lat' => ['required', 'numeric', 'between:-90,90'],
                'lng' => ['required', 'numeric', 'between:-180,180'],
            ],
            [],
            ['lat' => 'latitude', 'lng' => 'longitude']
        )->validate();

        $this->geoLat = round($lat, 7);
        $this->geoLng = round($lng, 7);
        $this->geoLabel = trim($label) ?: number_format($this->geoLat, 5) . ', ' . number_format($this->geoLng, 5);
        $this->geoFilterEnabled = true;
        $this->resetPage();
    }

    public function resetGeoFilter(): void
    {
        $this->geoFilterEnabled = false;
        $this->geoLat = null;
        $this->geoLng = null;
        $this->geoLabel = '';
        $this->resetPage();
        $this->dispatch('leads-geo-reset');
    }

    public function render()
    {
        $sources = $this->empresa->leads()->whereNotNull('source')->distinct()->pluck('source')->sort()->values();

        $query = $this->empresa->leads()
            ->when($this->search, fn ($q) => $q->where('nome', 'like', '%' . $this->search . '%'))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterSource, fn ($q) => $q->where('source', $this->filterSource))
            ->when($this->filterProspectingSearchId !== '', fn ($q) => $q->where('prospecting_search_id', (int) $this->filterProspectingSearchId))
            ->when($this->filterNearby, fn ($q) => $q->where('is_nearby', true))
            ->when($this->filterMinScore !== '', fn ($q) => $q->where('lead_score', '>=', (int) $this->filterMinScore));

        $geoActive = $this->geoFilterEnabled
            && $this->geoLat !== null
            && $this->geoLng !== null
            && $this->geoRadiusKm > 0;

        if ($geoActive) {
            $driver = $query->getConnection()->getDriverName();
            $lat = (float) $this->geoLat;
            $lng = (float) $this->geoLng;
            $radius = (float) $this->geoRadiusKm;

            if ($driver === 'sqlite') {
                $geo = app(GeoService::class);
                $perPage = 20;
                $page = Paginator::resolveCurrentPage('page');

                $all = (clone $query)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->get();

                $filtered = $all
                    ->map(function ($lead) use ($geo, $lat, $lng) {
                        $dist = $geo->haversine($lat, $lng, (float) $lead->latitude, (float) $lead->longitude);
                        $lead->setAttribute('geo_distance_km', $dist);
                        return $lead;
                    })
                    ->filter(fn ($lead) => (float) $lead->geo_distance_km <= $radius)
                    ->sort(function ($a, $b) {
                        $d = ($a->geo_distance_km <=> $b->geo_distance_km);
                        return $d !== 0 ? $d : ($b->lead_score <=> $a->lead_score);
                    })
                    ->values();

                $items = $filtered->slice(($page - 1) * $perPage, $perPage)->values();
                $leads = new LengthAwarePaginator(
                    $items,
                    $filtered->count(),
                    $perPage,
                    $page,
                    ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
                );
            } else {
                $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

                $leads = (clone $query)
                    ->whereNotNull('latitude')
                    ->whereNotNull('longitude')
                    ->selectRaw("leads.*, {$haversine} as geo_distance_km", [$lat, $lng, $lat])
                    ->having('geo_distance_km', '<=', $radius)
                    ->orderBy('geo_distance_km')
                    ->orderByDesc('lead_score')
                    ->paginate(20);
            }
        } else {
            $leads = (clone $query)
                ->orderByDesc('lead_score')
                ->paginate(20);
        }

        $hasFilters = $this->search
            || $this->filterStatus
            || $this->filterSource
            || $this->filterProspectingSearchId !== ''
            || $this->filterNearby
            || $this->filterMinScore !== ''
            || $this->geoFilterEnabled;

        return view('livewire.leads.leads-table', compact('leads', 'sources', 'hasFilters'));
    }
}
