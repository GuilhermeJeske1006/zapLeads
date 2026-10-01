<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Services\Enrichment\LeadEnrichmentService;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Side panel with everything known about a lead: score and why, decision maker, contacts and where
 * each came from, signals, the suggested messages and what happened so far. Opened from any list
 * with the "open-lead-dossier" event; actions tell the lists with "lead-updated".
 */
class LeadDossier extends Component
{
    public Empresa $empresa;

    #[Locked]
    public ?int $leadId = null;

    #[On('open-lead-dossier')]
    public function abrir(int $id): void
    {
        // Only the empresa's own leads: the id comes from the browser.
        $this->leadId = $this->empresa->leads()->whereKey($id)->value('id');
    }

    public function fechar(): void
    {
        $this->leadId = null;
    }

    public function gerarAbordagem(): void
    {
        try {
            app(OutreachService::class)->request($this->lead());
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

        $this->dispatch('outreach-requested');
        $this->dispatch('lead-updated');
    }

    public function escolherVariante(string $angulo): void
    {
        if ($draft = $this->pendingDraft($this->lead())) {
            app(OutreachService::class)->chooseVariant($draft, $angulo);
            $this->dispatch('outreach-requested');
        }
    }

    public function buscarContatos(): void
    {
        $lead = $this->lead();

        app(LeadEnrichmentService::class)->queue($lead);
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type: 'success', message: __('messages.enrichment_queued', ['nome' => $lead->nome]));
    }

    /** "Ele respondeu": the lead answered a message the system didn't see. */
    public function registrarResposta(): void
    {
        app(OutreachService::class)->registerReply($this->lead());
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type: 'success', message: __('messages.reply_registered'));
    }

    public function alterarStatus(string $status): void
    {
        app(OutreachService::class)->setStatus($this->lead(), $status);
        $this->dispatch('lead-updated');
        $this->dispatch('toast', type: 'success', message: __('messages.status_updated'));
    }

    private function lead(): Lead
    {
        return $this->empresa->leads()->findOrFail($this->leadId);
    }

    private function pendingDraft(Lead $lead): ?OutreachDraft
    {
        return $this->empresa->outreachDrafts()
            ->where('lead_id', $lead->id)
            ->whereIn('status', OutreachDraft::PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * What happened to the lead, oldest first.
     *
     * @return Collection<int, array{when: \Carbon\CarbonInterface, text: string}>
     */
    private function timeline(Lead $lead): Collection
    {
        $events = collect([[
            'when' => $lead->created_at,
            'text' => match (true) {
                $lead->source === 'internal'         => __('messages.timeline_from_catalog'),
                $lead->prospectingSearch !== null    => __('messages.timeline_found', ['busca' => $lead->prospectingSearch->tipo_cliente]),
                $lead->source === 'internet'         => __('messages.timeline_found_generic'),
                default                              => __('messages.timeline_added'),
            },
        ]]);

        if ($lead->enriched_at) {
            $events->push(['when' => $lead->enriched_at, 'text' => __('messages.timeline_enriched')]);
        }

        foreach ($lead->outreachAttempts as $attempt) {
            $events->push([
                'when' => $attempt->created_at,
                'text' => __($attempt->etapa > 0 ? 'messages.timeline_follow_up' : 'messages.timeline_approached', [
                    'canal' => __('messages.timeline_channel_' . $attempt->canal),
                    'etapa' => $attempt->etapa,
                ]),
            ]);

            if ($attempt->responded_at) {
                $events->push(['when' => $attempt->responded_at, 'text' => __('messages.timeline_replied')]);
            }
        }

        if ($lead->opted_out_at) {
            $events->push(['when' => $lead->opted_out_at, 'text' => __('messages.timeline_opted_out')]);
        }

        return $events->filter(fn (array $event) => $event['when'] !== null)->sortBy('when')->values();
    }

    public function render()
    {
        $lead = $this->leadId
            ? $this->empresa->leads()
                ->with(['prospectingSearch:id,tipo_cliente', 'outreachAttempts' => fn ($q) => $q->orderBy('id')])
                ->find($this->leadId)
            : null;

        if (!$lead) {
            return view('livewire.leads.lead-dossier', ['lead' => null]);
        }

        $draft = $this->pendingDraft($lead);

        return view('livewire.leads.lead-dossier', [
            'lead'      => $lead,
            'contatos'  => $lead->contacts()->orderByDesc('is_primary')->orderByDesc('confianca')->get(),
            'draft'     => $draft,
            'timeline'  => $this->timeline($lead),
            'waitingReply' => $lead->outreachAttempts->whereNull('responded_at')->isNotEmpty(),
            // Follows a contact lookup or a message being written in the background.
            'busy'      => in_array($lead->enrichment_status, ['pending', 'running'], true) || $draft?->status === 'generating',
        ]);
    }
}
