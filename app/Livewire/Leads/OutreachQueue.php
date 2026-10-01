<?php

namespace App\Livewire\Leads;

use App\Models\Empresa;
use App\Models\OutreachDraft;
use App\Services\Prospecting\OutreachException;
use App\Services\Prospecting\OutreachScheduler;
use App\Services\Prospecting\OutreachService;
use Livewire\Attributes\On;
use Livewire\Component;

/** First messages waiting for review: edit, then send through the API or from the user's own WhatsApp. */
class OutreachQueue extends Component
{
    public Empresa $empresa;

    /** @var array<int, int|string> draft ids ticked for batch approval */
    public array $selecionados = [];

    #[On('outreach-requested')]
    public function refresh(): void
    {
        // Re-renders with the new draft.
    }

    public function salvarTexto(int $draftId, string $texto): void
    {
        $draft = $this->draft($draftId);

        if ($draft->status === 'draft') {
            $draft->update(['texto_final' => trim($texto)]);
        }
    }

    public function enviarPelaApi(int $draftId, string $texto): void
    {
        try {
            $draft = app(OutreachService::class)->approve($this->draft($draftId), $texto);
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('messages.outreach_scheduled', ['when' => $this->localTime($draft)]));
    }

    public function aprovarSelecionados(): void
    {
        $outreach = app(OutreachService::class);
        $scheduled = 0;
        $refused = 0;

        $drafts = $this->empresa->outreachDrafts()->whereKey($this->selecionados)->where('status', 'draft')->get();
        foreach ($drafts as $draft) {
            try {
                $outreach->approve($draft);
                $scheduled++;
            } catch (OutreachException) {
                $refused++;
            }
        }

        $message = __('messages.outreach_batch_scheduled', ['count' => $scheduled]);
        if ($refused > 0) {
            $message .= ' ' . __('messages.outreach_batch_refused', ['count' => $refused]);
        }

        $this->selecionados = [];
        $this->dispatch('toast', type: $scheduled ? 'success' : 'error', message: $message);
    }

    /** Called when the user clicks the wa.me link: the message goes out from their own phone. */
    public function enviadoPeloMeuWhatsApp(int $draftId, string $texto): void
    {
        try {
            app(OutreachService::class)->markAssisted($this->draft($draftId), $texto);
        } catch (OutreachException $e) {
            $this->dispatch('toast', type: 'error', message: __($e->messageKey()));
            return;
        }

        $this->dispatch('toast', type: 'success', message: __('messages.outreach_assisted_recorded'));
    }

    public function pular(int $draftId): void
    {
        app(OutreachService::class)->skip($this->draft($draftId));
        $this->selecionados = array_values(array_diff($this->selecionados, [$draftId, (string) $draftId]));
    }

    public function tentarDeNovo(int $draftId): void
    {
        app(OutreachService::class)->retry($this->draft($draftId));
    }

    public function alternarEnvioAutomatico(): void
    {
        $this->empresa->update(['prospeccao_envio_automatico' => !$this->empresa->prospeccao_envio_automatico]);
    }

    private function draft(int $id): OutreachDraft
    {
        return $this->empresa->outreachDrafts()->findOrFail($id);
    }

    private function localTime(OutreachDraft $draft): string
    {
        return $draft->scheduled_for->setTimezone($this->empresa->timezone ?: config('app.timezone'))->format('d/m H:i');
    }

    public function render()
    {
        $drafts = $this->empresa->outreachDrafts()
            ->with('lead', 'whatsappChannel')
            ->where(fn ($q) => $q
                ->whereIn('status', OutreachDraft::PENDING)
                ->orWhere(fn ($q) => $q->where('status', 'failed')->where('updated_at', '>=', now()->subWeek())))
            ->orderBy('id')
            ->limit(50)
            ->get();

        $outreach = app(OutreachService::class);
        $plans = $drafts->where('status', 'draft')->mapWithKeys(fn ($d) => [$d->id => $outreach->deliveryPlan($d)]);

        $channel = $this->empresa->defaultChannel();
        $usedToday = $channel ? app(OutreachScheduler::class)->usedToday($channel) : null;

        return view('livewire.leads.outreach-queue', [
            'drafts'    => $drafts,
            'plans'     => $plans,
            'channel'   => $channel,
            'usedToday' => $usedToday,
            'timezone'  => $this->empresa->timezone ?: config('app.timezone'),
        ]);
    }
}
