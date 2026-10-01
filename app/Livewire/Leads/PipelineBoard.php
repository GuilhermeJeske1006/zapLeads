<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Models\Lead;
use App\Services\Prospecting\OutreachService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Prospecção → Acompanhar: the funnel as a board, one column per lead status. Cards are dragged
 * between columns (wire:sort) or moved with the select on each card (keyboard).
 */
class PipelineBoard extends Component
{
    /** Cards per column; the rest are in /leads, filtered by that status. */
    public const PER_COLUMN = 30;

    public const ORIGENS = ['prospeccao' => ['internet', 'manual'], 'catalogo' => ['internal']];

    public Empresa $empresa;

    #[Url(as: 'origem')]
    public string $origem = '';

    public string $busca = '';

    #[On('lead-updated')]
    #[On('outreach-requested')]
    public function refresh(): void
    {
        // Re-renders with the lead's new status.
    }

    /** wire:sort handler: the card $leadId was dropped in the column $status. */
    public function mover(int|string $leadId, int $position, string $status): void
    {
        $this->moverPara((int) $leadId, $status);
    }

    public function moverPara(int $leadId, string $status): void
    {
        if (!array_key_exists($status, Lead::STATUSES)) {
            return;
        }

        $lead = $this->empresa->leads()->find($leadId);
        if (!$lead || $lead->status === $status) {
            return;
        }

        app(OutreachService::class)->setStatus($lead, $status);
        $this->dispatch('toast', type: 'success', message: __('messages.lead_moved', ['nome' => $lead->nome, 'etapa' => Lead::statusLabel($status)]));
    }

    public function render()
    {
        $base = $this->empresa->leads()
            ->when(isset(self::ORIGENS[$this->origem]), fn ($q) => $q->whereIn('source', self::ORIGENS[$this->origem]))
            ->when(trim($this->busca) !== '', fn ($q) => $q->where('nome', 'like', '%' . trim($this->busca) . '%'));

        $counts = (clone $base)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $columns = [];
        foreach (array_keys(Lead::STATUSES) as $status) {
            $columns[$status] = (clone $base)
                ->where('status', $status)
                ->with('primaryContact:id,lead_id,origem')
                // New leads by who is most worth approaching; the others by latest movement.
                ->when($status === 'novo', fn ($q) => $q->orderByDesc('lead_score'), fn ($q) => $q->orderByDesc('updated_at'))
                ->limit(self::PER_COLUMN)
                ->get(['id', 'nome', 'cidade', 'status', 'lead_score', 'enrichment_status', 'contact_confidence', 'opted_out_at', 'updated_at']);
        }

        return view('livewire.leads.pipeline-board', [
            'columns' => $columns,
            'counts'  => $counts,
        ]);
    }
}
