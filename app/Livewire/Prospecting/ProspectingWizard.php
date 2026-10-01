<?php

namespace App\Livewire\Prospecting;

use App\Jobs\FindInternetLeadsJob;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\ProspectingSearch;
use App\Services\Geo\GeocodingService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Prospecção as a funnel: 1 Perfil (who and where, pre-filled from Minha Empresa) → 2 Resultados
 * (live progress, map and list by score) → 3 Revisar abordagens (OutreachQueue) → 4 Acompanhar
 * (PipelineBoard). Past searches can be reopened or run again.
 */
class ProspectingWizard extends Component
{
    public const STEPS = ['perfil', 'resultados', 'abordagens', 'acompanhar'];

    /** Minimum Google rating options for the "Nota mín." filter. */
    public const RATINGS = ['3.5', '4', '4.5'];

    private const MAX_RESULTS = 60;

    /** "Selecionar top": how many of the best leads get ticked. */
    public const TOP = 20;

    /** Messages written per click (each is a paid AI call). */
    public const BULK_LIMIT = 30;

    private const HISTORY = 15;

    public Empresa $empresa;

    #[Url(as: 'passo')]
    public string $passo = 'perfil';

    /** Not #[Locked]: the URL sets it (and back/forward too). Every read goes through search(), scoped to the empresa. */
    #[Url(as: 'busca')]
    public ?int $searchId = null;

    // Passo 1 — perfil
    public string $descricaoEmpresa = '';
    /** @var list<string> */
    public array $tiposCliente = [];
    public string $onde = 'empresa';
    public string $localBusca = '';
    public ?float $customLat = null;
    public ?float $customLng = null;
    public string $customLocationLabel = '';
    public float $raioBuscaKm = 5;

    // Filtros: chosen in step 1 with the search, applied (and changeable) in step 2.
    public bool $soWhatsApp = true;
    public bool $semSite = false;
    public string $notaMin = '';

    // Passo 2 — resultados
    /** @var list<int> lead ids in the order first shown: scores change while contacts are found, rows don't jump. */
    public array $ordem = [];
    /** @var list<int|string> */
    public array $selecionados = [];

    public function mount(): void
    {
        $this->descricaoEmpresa = (string) $this->empresa->descricao_empresa;
        $this->tiposCliente = self::splitTipos($this->empresa->tipo_cliente_alvo);
        $this->raioBuscaKm = (float) ($this->empresa->raio_atendimento ?: 5);
        $this->onde = $this->hasCompanyLocation() ? 'empresa' : 'outro';

        if (!in_array($this->passo, self::STEPS, true)) {
            $this->passo = 'perfil';
        }

        $search = $this->searchId ? $this->search() : null;
        if ($search) {
            $this->openSearch($search);
        } else {
            $this->searchId = null;
            if ($this->passo === 'resultados') {
                $this->passo = 'perfil';
            }
        }
    }

    public function getListeners(): array
    {
        return [
            "echo-private:empresa.{$this->empresa->id}.prospecting,.search.updated" => 'onSearchUpdated',
            'lead-updated' => '$refresh',
        ];
    }

    public function irPara(string $passo): void
    {
        if (!in_array($passo, self::STEPS, true) || ($passo === 'resultados' && !$this->search())) {
            return;
        }

        $this->passo = $passo;
        if ($passo === 'resultados') {
            $this->pushMap();
        }
    }

    public function updatedPasso(string $passo): void
    {
        if (!in_array($passo, self::STEPS, true)) {
            $this->passo = 'perfil';
        }
    }

    public function updatedSearchId(): void
    {
        if ($search = $this->search()) {
            $this->openSearch($search);
        } else {
            $this->searchId = null;
        }
    }

    // ── Passo 1 ────────────────────────────────────────────────────────────

    #[On('prospecting-map:location')]
    public function setCustomLocation($lat = null, $lng = null, string $label = ''): void
    {
        if (!is_numeric($lat) || !is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return;
        }

        $this->onde = 'outro';
        $this->customLat = (float) $lat;
        $this->customLng = (float) $lng;
        $this->customLocationLabel = mb_substr(trim($label) ?: number_format($this->customLat, 5) . ', ' . number_format($this->customLng, 5), 0, 150);
        $this->localBusca = $this->customLocationLabel;
    }

    public function updatedLocalBusca(): void
    {
        // Typed something else: the point picked on the map no longer applies.
        if (trim($this->localBusca) !== $this->customLocationLabel) {
            $this->customLat = $this->customLng = null;
            $this->customLocationLabel = '';
        }
    }

    public function buscar(): void
    {
        $this->tiposCliente = self::cleanTipos($this->tiposCliente);

        $this->validate([
            'descricaoEmpresa' => 'required|string|min:10|max:2000',
            'tiposCliente'     => 'required|array|min:1|max:10',
            'tiposCliente.*'   => 'string|min:2|max:80',
            'onde'             => 'required|in:empresa,outro',
            'localBusca'       => 'required_if:onde,outro|nullable|string|min:2|max:150',
            'raioBuscaKm'      => 'required|numeric|min:1|max:50',
            'notaMin'          => 'nullable|in:' . implode(',', self::RATINGS),
        ], [
            'descricaoEmpresa.required' => __('messages.prospecting_describe_required'),
            'descricaoEmpresa.min'      => __('messages.prospecting_describe_required'),
            'tiposCliente.required'     => __('messages.prospecting_customer_required'),
            'tiposCliente.min'          => __('messages.prospecting_customer_required'),
            'localBusca.required_if'    => __('messages.prospecting_place_required'),
        ]);

        if ($this->onde === 'empresa' && !$this->hasCompanyLocation()) {
            $this->addError('onde', __('messages.prospecting_company_address_missing'));
            return;
        }

        if ($this->onde === 'outro' && !$this->resolveCustomLocation()) {
            $this->addError('localBusca', __('messages.prospecting_place_not_found'));
            return;
        }

        // The ICP lives in Minha Empresa → Vendas; a search only fills it the first time.
        $this->empresa->update(array_filter([
            'descricao_empresa' => $this->empresa->descricao_empresa ? null : $this->descricaoEmpresa,
            'tipo_cliente_alvo' => $this->empresa->tipo_cliente_alvo ? null : implode(', ', $this->tiposCliente),
        ]));

        $custom = $this->onde === 'outro';

        $this->start(ProspectingSearch::create([
            'empresa_id'        => $this->empresa->id,
            'descricao_empresa' => $this->descricaoEmpresa,
            'tipo_cliente'      => implode(', ', $this->tiposCliente),
            'latitude'          => $custom ? $this->customLat : (float) $this->empresa->latitude,
            'longitude'         => $custom ? $this->customLng : (float) $this->empresa->longitude,
            'radius_km'         => $this->raioBuscaKm,
            'local_label'       => $custom ? $this->customLocationLabel : null,
            'filtros'           => $this->filtros(),
            'keywords'          => [],
            'status'            => 'queued',
        ]));
    }

    /** "Buscar de novo": same customer, place, radius and filters, as a new search (new Google results). */
    public function buscarDeNovo(int $searchId): void
    {
        $old = $this->empresa->prospectingSearches()->find($searchId);
        if (!$old) {
            return;
        }

        $this->start($old->replicate(['keywords', 'status', 'stage', 'progress', 'results_count', 'error'])->fill([
            'keywords' => [],
            'status'   => 'queued',
        ]));
    }

    public function abrirBusca(int $searchId): void
    {
        if ($search = $this->empresa->prospectingSearches()->find($searchId)) {
            $this->openSearch($search);
            $this->passo = 'resultados';
            $this->pushMap();
        }
    }

    // ── Passo 2 ────────────────────────────────────────────────────────────

    /** Polling fallback and Reverb/Pusher event: the search moved on. */
    public function onSearchUpdated(array $payload = []): void
    {
        if (isset($payload['id']) && (int) $payload['id'] !== $this->searchId) {
            return;
        }

        $this->syncResults();
    }

    public function atualizar(): void
    {
        $this->syncResults();
    }

    public function ordenarPorScore(): void
    {
        if ($search = $this->search()) {
            $this->ordem = $this->rankedIds($search);
            $this->pushMap();
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['soWhatsApp', 'semSite', 'notaMin'], true)) {
            if (!in_array($this->notaMin, ['', ...self::RATINGS], true)) {
                $this->notaMin = '';
            }
            $this->pushMap();
        }
    }

    public function selecionarTop(): void
    {
        $this->selecionados = $this->visibleLeads()
            ->filter(fn (Lead $lead) => self::approachable($lead))
            ->take(self::TOP)
            ->pluck('id')
            ->all();
    }

    public function limparSelecao(): void
    {
        $this->selecionados = [];
    }

    /** Writes the first message of each ticked lead in the background and opens the review queue. */
    public function gerarAbordagens(): void
    {
        if (count($this->selecionados) > self::BULK_LIMIT) {
            $this->dispatch('toast', type: 'error', message: __('messages.bulk_outreach_limit', ['max' => self::BULK_LIMIT]));
            return;
        }

        $leads = $this->empresa->leads()->whereKey(array_map('intval', $this->selecionados))->get();

        $outreach = app(OutreachService::class);
        $queued = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            // A first message only to who wasn't approached yet; the others are in the pipeline.
            if ($lead->status !== 'novo') {
                $skipped++;
                continue;
            }

            try {
                $outreach->request($lead);
                $queued++;
            } catch (OutreachException) {
                $skipped++;
            }
        }

        $this->selecionados = [];

        if ($queued === 0) {
            $this->dispatch('toast', type: 'error', message: __('messages.bulk_outreach_none'));
            return;
        }

        $message = trans_choice('messages.bulk_outreach_queued', $queued, ['count' => $queued]);
        if ($skipped > 0) {
            $message .= ' ' . trans_choice('messages.bulk_outreach_skipped', $skipped, ['count' => $skipped]);
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->passo = 'abordagens';
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function start(ProspectingSearch $search): void
    {
        $search->save();

        $this->searchId = $search->id;
        $this->ordem = [];
        $this->selecionados = [];
        $this->applyFilters($search);
        $this->passo = 'resultados';

        $custom = $search->local_label !== null;

        FindInternetLeadsJob::dispatch(
            $search->id,
            self::MAX_RESULTS,
            $custom ? $search->latitude : null,
            $custom ? $search->longitude : null,
            (string) $search->local_label,
        );

        $this->pushMap();
    }

    private function openSearch(ProspectingSearch $search): void
    {
        $this->searchId = $search->id;
        $this->selecionados = [];
        $this->applyFilters($search);
        $this->ordem = $search->status === 'done' ? $this->rankedIds($search) : [];
    }

    /** Loads the list once the results exist, and refreshes the map while scores and contacts change. */
    private function syncResults(): void
    {
        $search = $this->search();
        if (!$search) {
            return;
        }

        if ($search->status === 'done' && $this->ordem === []) {
            $this->ordem = $this->rankedIds($search);
        }

        $this->pushMap();
    }

    /** @return list<int> */
    private function rankedIds(ProspectingSearch $search): array
    {
        return $this->empresa->leads()
            ->where('prospecting_search_id', $search->id)
            ->orderByDesc('lead_score')
            ->orderBy('id')
            ->limit(self::MAX_RESULTS + 20)
            ->pluck('id')
            ->all();
    }

    private function applyFilters(ProspectingSearch $search): void
    {
        $filtros = $search->filtros ?? [];
        $this->soWhatsApp = (bool) ($filtros['so_whatsapp'] ?? false);
        $this->semSite = (bool) ($filtros['sem_site'] ?? false);
        $this->notaMin = in_array((string) ($filtros['nota_min'] ?? ''), self::RATINGS, true) ? (string) $filtros['nota_min'] : '';
    }

    private function filtros(): array
    {
        return [
            'so_whatsapp' => $this->soWhatsApp,
            'sem_site'    => $this->semSite,
            'nota_min'    => $this->notaMin !== '' ? $this->notaMin : null,
        ];
    }

    private function search(): ?ProspectingSearch
    {
        return $this->searchId ? $this->empresa->prospectingSearches()->find($this->searchId) : null;
    }

    private function hasCompanyLocation(): bool
    {
        return $this->empresa->latitude && $this->empresa->longitude;
    }

    /** A place typed by hand is geocoded near the empresa's city; a point picked on the map is used as is. */
    private function resolveCustomLocation(): bool
    {
        $typed = trim($this->localBusca);
        if ($this->customLat !== null && $this->customLng !== null && $typed === trim($this->customLocationLabel)) {
            return true;
        }

        $query = $typed;
        $city = trim((string) $this->empresa->cidade);
        if ($city !== '' && !str_contains(mb_strtolower($query), mb_strtolower($city))) {
            $query .= ' ' . $city;
        }

        $hit = app(GeocodingService::class)->geocode($query);
        if (!$hit) {
            return false;
        }

        $this->customLat = (float) $hit['lat'];
        $this->customLng = (float) $hit['lng'];
        $this->customLocationLabel = mb_substr((string) ($hit['display_name'] ?? $typed), 0, 150);
        $this->localBusca = $this->customLocationLabel;

        return true;
    }

    /** The search's leads in list order, after the filters. */
    private function visibleLeads(): Collection
    {
        if ($this->ordem === []) {
            return collect();
        }

        $position = array_flip($this->ordem);

        return $this->empresa->leads()
            ->whereKey($this->ordem)
            ->with('primaryContact:id,lead_id,origem')
            ->get()
            ->filter(fn (Lead $lead) => $this->passesFilters($lead))
            ->sortBy(fn (Lead $lead) => $position[$lead->id] ?? PHP_INT_MAX)
            ->values();
    }

    private function passesFilters(Lead $lead): bool
    {
        if ($this->soWhatsApp && !self::probableWhatsApp($lead)) {
            return false;
        }

        if ($this->semSite && trim((string) $lead->website) !== '') {
            return false;
        }

        if ($this->notaMin !== '' && (float) ($lead->ai_insights['rating'] ?? 0) < (float) $this->notaMin) {
            return false;
        }

        return true;
    }

    /** A lead whose contacts are still being looked up stays listed until it's done (landlines may turn up a WhatsApp). */
    public static function probableWhatsApp(Lead $lead): bool
    {
        return in_array($lead->enrichment_status, ['pending', 'running'], true) || $lead->hasProbableWhatsApp();
    }

    private static function approachable(Lead $lead): bool
    {
        return $lead->status === 'novo' && $lead->telefone_e164 !== null && !$lead->isOptedOut();
    }

    /** Markers for the results map, after a change the list shows too. */
    private function pushMap(): void
    {
        $search = $this->search();
        if (!$search) {
            return;
        }

        $this->dispatch('prospecting-map:results',
            center: ['lat' => $search->latitude, 'lng' => $search->longitude],
            radiusKm: $search->radius_km,
            markers: self::markers($this->visibleLeads()),
        );
    }

    /** Only what the map needs, never the whole lead. */
    private static function markers(Collection $leads): array
    {
        return $leads
            ->filter(fn (Lead $lead) => $lead->latitude && $lead->longitude)
            ->map(fn (Lead $lead) => [
                'id'    => $lead->id,
                'nome'  => $lead->nome,
                'lat'   => $lead->latitude,
                'lng'   => $lead->longitude,
                'score' => (int) $lead->lead_score,
            ])
            ->values()
            ->all();
    }

    /** @return list<string> */
    private static function splitTipos(?string $text): array
    {
        return self::cleanTipos(preg_split('/[,;\n]+/', (string) $text) ?: []);
    }

    /** @return list<string> */
    private static function cleanTipos(array $tipos): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($tipo) => mb_substr(trim((string) $tipo), 0, 80),
            $tipos,
        ), fn (string $tipo) => $tipo !== '')));
    }

    public function render()
    {
        $search = $this->search();
        $leads = $this->passo === 'resultados' ? $this->visibleLeads() : collect();

        return view('livewire.prospecting.prospecting-wizard', [
            'search'     => $search,
            'leads'      => $leads,
            'markers'    => self::markers($leads),
            'totalLeads' => count($this->ordem),
            'history'    => $this->empresa->prospectingSearches()->latest('id')->limit(self::HISTORY)->get(),
            'pendingDrafts' => $this->empresa->outreachDrafts()->whereIn('status', ['generating', 'draft'])->count(),
            'enrichment' => $search?->stage === 'enriching' ? $search->enrichmentProgress() : null,
            'placesLimited' => !config('services.google_places.key'),
        ]);
    }
}
