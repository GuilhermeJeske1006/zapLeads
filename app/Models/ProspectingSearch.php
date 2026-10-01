<?php

namespace App\Models;

use App\Events\ProspectingSearchUpdated;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;

class ProspectingSearch extends Model
{
    use HasFactory;

    /**
     * What the search is doing, in order. "status" says whether the results exist (done) or the
     * search failed; "stage" goes on after status=done while contacts are looked up in the background.
     */
    public const STAGES = ['keywords', 'searching', 'ranking', 'enriching', 'done'];

    protected $fillable = [
        'empresa_id',
        'descricao_empresa',
        'tipo_cliente',
        'latitude',
        'longitude',
        'radius_km',
        'local_label',
        'filtros',
        'keywords',
        'status',
        'stage',
        'progress',
        'custos',
        'results_count',
        'error',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_km' => 'float',
        'keywords' => 'array',
        'filtros' => 'array',
        'progress' => 'array',
        'custos' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'prospecting_search_id');
    }

    /** error holds a translation key; searches from before Fase 5 hold the text itself. */
    public function errorMessage(): ?string
    {
        if ($this->error === null) {
            return null;
        }

        return Lang::has($this->error) ? __($this->error) : $this->error;
    }

    /** Still producing something the screen should follow: results, scores or contacts. */
    public function isActive(): bool
    {
        return in_array($this->status, ['queued', 'running'], true)
            || ($this->status === 'done' && $this->stage !== null && $this->stage !== 'done');
    }

    /** Moves to $stage, merges $progress counts and tells the screen. At "done" the costs are final. */
    public function advance(string $stage, array $progress = []): void
    {
        $this->update(['stage' => $stage, 'progress' => array_merge($this->progress ?? [], $progress)]);
        if ($stage === 'done') {
            $this->refreshCosts();
        }
        $this->broadcastProgress();
    }

    /** Sums what the search spent (keywords, Places, ranking, its enrichment batch) into custos. */
    public function refreshCosts(): void
    {
        $this->update(['custos' => ApiUsage::summarize(ApiUsage::where('prospecting_search_id', $this->id))
            + ['atualizado_em' => now()->toIso8601String()]]);
    }

    /** Leads of this search worth approaching (Lead::isQualified), for the cost per qualified lead. */
    public function qualifiedLeadsCount(): int
    {
        return $this->leads()
            ->get(['id', 'enrichment_status', 'contact_confidence', 'telefone_e164', 'ai_insights'])
            ->filter(fn (Lead $lead) => $lead->isQualified())
            ->count();
    }

    /**
     * Leads whose contact lookup finished, out of those queued, while the search is enriching.
     *
     * @return array{done: int, total: int}|null
     */
    public function enrichmentProgress(): ?array
    {
        $total = (int) ($this->progress['enriquecer'] ?? 0);
        if ($total === 0) {
            return null;
        }

        $batch = isset($this->progress['batch_id']) ? Bus::findBatch($this->progress['batch_id']) : null;

        // A failed job stays in pending_jobs (allowFailures); it is finished all the same. Without the
        // batch (its id is saved right after dispatch), the leads still waiting tell the same.
        $done = $batch instanceof Batch
            ? $batch->processedJobs() + $batch->failedJobs
            : $total - $this->leads()->whereIn('enrichment_status', ['pending', 'running'])->count();

        return ['done' => max(0, min($total, $done)), 'total' => $total];
    }

    /**
     * Pushes the change to the empresa's screens (Reverb/Pusher). A broadcaster that is down must not
     * fail the search or the enrichment job: the screen also polls.
     */
    public function broadcastProgress(): void
    {
        try {
            // event() sends here; broadcast() would send from PendingBroadcast's destructor.
            event(new ProspectingSearchUpdated($this));
        } catch (\Throwable $e) {
            Log::warning('Prospecting progress broadcast failed', ['search_id' => $this->id, 'error' => $e->getMessage()]);
        }
    }
}
