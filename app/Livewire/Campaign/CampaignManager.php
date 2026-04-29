<?php

namespace App\Livewire\Campaign;

use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use App\Models\Loja;
use App\Services\AIService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CampaignManager extends Component
{
    use WithFileUploads, WithPagination;

    public Loja $loja;
    public bool $showModal = false;
    public bool $showAISuggest = false;
    public array $aiSuggestion = [];

    public string $nome = '';
    public string $mensagem = '';
    public $imagem = null;
    public bool $filterNearby = false;
    public int $minScore = 0;

    public function openModal(): void
    {
        $this->reset('nome', 'mensagem', 'imagem', 'filterNearby', 'minScore', 'aiSuggestion', 'showAISuggest');
        $this->showModal = true;
    }

    protected function rules(): array
    {
        return [
            'nome' => 'required|string|max:255',
            'mensagem' => 'required|string|max:4096',
            'imagem' => 'nullable|image|mimes:jpeg,png,webp|max:2048',
            'filterNearby' => 'boolean',
            'minScore' => 'integer|min:0|max:100',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        $filtros = [
            'is_nearby' => $this->filterNearby,
            'min_score' => $this->minScore,
        ];

        $imagemPath = null;
        if ($this->imagem) {
            $imagemPath = $this->imagem->store('campaigns', 'public');
        }

        $campaign = Campaign::create([
            'loja_id' => $this->loja->id,
            'nome' => $this->nome,
            'mensagem' => $this->mensagem,
            'imagem' => $imagemPath,
            'filtros' => $filtros,
            'status' => 'draft',
        ]);

        $leads = $this->loja->leads()
            ->when($this->filterNearby, fn ($q) => $q->where('is_nearby', true))
            ->when($this->minScore > 0, fn ($q) => $q->where('lead_score', '>=', $this->minScore))
            ->get();

        $campaign->leads()->attach($leads->pluck('id'), ['status' => 'pending']);

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: __('messages.campaign_created'));
    }

    public function dispatch_campaign(int $id): void
    {
        $campaign = Campaign::findOrFail($id);
        $campaign->update(['status' => 'scheduled']);
        ProcessCampaignJob::dispatch($campaign);
        $this->dispatch('toast', type: 'success', message: __('messages.campaign_dispatched'));
    }

    public function getAISuggestion(AIService $ai): void
    {
        try {
            $leads = $this->loja->leads()->limit(100)->get()->toArray();
            $this->aiSuggestion = $ai->sugerirCampanha($leads, $this->mensagem);
            $this->showAISuggest = true;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AIService sugerirCampanha failed', ['error' => $e->getMessage()]);
            $this->dispatch('toast', type: 'error', message: 'Limite da API atingido. Tente novamente em alguns minutos.');
        }
    }

    public function applyAISuggestion(): void
    {
        if (!empty($this->aiSuggestion['mensagem'])) {
            $this->mensagem = $this->aiSuggestion['mensagem'];
        }
        $this->showAISuggest = false;
    }

    public function render()
    {
        $campaigns = Campaign::where('loja_id', $this->loja->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.campaign.campaign-manager', compact('campaigns'));
    }
}
