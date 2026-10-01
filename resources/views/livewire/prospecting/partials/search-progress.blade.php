{{-- What the search is doing, live: terms, companies, ranking, contacts. --}}
@php
    $stageIndex = array_search($search->stage, \App\Models\ProspectingSearch::STAGES, true);
    $stageIndex = $search->status === 'done' && $search->stage === null ? count(\App\Models\ProspectingSearch::STAGES) : $stageIndex;
    // Searches from before the live progress have no counts: they come from the search itself.
    $legacy = empty($search->progress);
    $progress = ($search->progress ?? []) + ['keywords' => count($search->keywords ?? []), 'empresas' => (int) $search->results_count];
    $state = function (string $stage) use ($search, $stageIndex): string {
        if ($search->status === 'failed') {
            return 'idle';
        }
        $index = array_search($stage, \App\Models\ProspectingSearch::STAGES, true);
        if ($stageIndex === false) {
            return 'idle';
        }
        return $index < $stageIndex ? 'done' : ($index === $stageIndex ? 'active' : 'idle');
    };
    $items = [
        'keywords'  => $state('keywords') === 'done'
            ? trans_choice('messages.progress_keywords_done', (int) ($progress['keywords'] ?? 0), ['count' => (int) ($progress['keywords'] ?? 0)])
            : __('messages.progress_keywords'),
        'searching' => $state('searching') === 'done'
            ? trans_choice('messages.progress_searching_done', (int) ($progress['empresas'] ?? 0), ['count' => (int) ($progress['empresas'] ?? 0)])
            : __('messages.progress_searching'),
        'ranking'   => $state('ranking') === 'done' ? __('messages.progress_ranking_done') : __('messages.progress_ranking'),
        'enriching' => $enrichment
            ? __('messages.progress_enriching_count', ['done' => $enrichment['done'], 'total' => $enrichment['total']])
            : match (true) {
                $state('enriching') !== 'done'  => __('messages.progress_enriching'),
                isset($progress['enriquecer'])  => trans_choice('messages.progress_enriching_done', (int) $progress['enriquecer'], ['count' => (int) $progress['enriquecer']]),
                default                         => __('messages.progress_enriching_skipped'),
            },
    ];
    if ($legacy) {
        unset($items['enriching']);
    }
@endphp

<section aria-labelledby="search-progress-title" class="bg-gray-900 border border-gray-800 rounded-2xl p-4 sm:p-5 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="search-progress-title" class="text-sm font-semibold text-white break-words">{{ $search->tipo_cliente }}</h2>
            <p class="text-xs text-gray-400">
                {{ number_format((float) $search->radius_km, 1, ',', '') }} km
                · {{ $search->local_label ? \Illuminate\Support\Str::limit($search->local_label, 60) : __('messages.where_company_address') }}
                · <time datetime="{{ $search->created_at->toIso8601String() }}">{{ $search->created_at->setTimezone($timezone)->format('d/m H:i') }}</time>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="irPara('perfil')"
                    class="px-3 py-1.5 text-xs text-gray-200 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                {{ __('messages.new_search') }}
            </button>
            @if (!$search->isActive())
                <button type="button" wire:click="buscarDeNovo({{ $search->id }})" wire:confirm="{{ __('messages.search_again_confirm') }}"
                        class="px-3 py-1.5 text-xs text-emerald-200 bg-emerald-600/15 hover:bg-emerald-600/25 border border-emerald-600/40 rounded-lg
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    {{ __('messages.search_again') }}
                </button>
            @endif
        </div>
    </div>

    @if ($search->status === 'failed')
        <p role="alert" class="text-sm text-red-300">{{ $search->errorMessage() }}</p>
    @else
        <ol class="grid grid-cols-1 sm:grid-cols-2 {{ count($items) === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3' }} gap-2" aria-live="polite">
            @foreach ($items as $stage => $text)
                @php $s = $state($stage); @endphp
                <li class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm
                           {{ $s === 'done' ? 'bg-emerald-500/10 text-emerald-200' : ($s === 'active' ? 'bg-gray-800 text-white' : 'bg-gray-800/40 text-gray-400') }}">
                    @if ($s === 'done')
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span class="sr-only">{{ __('messages.progress_state_done') }}:</span>
                    @elseif ($s === 'active')
                        <svg class="w-4 h-4 shrink-0 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span class="sr-only">{{ __('messages.progress_state_active') }}:</span>
                    @else
                        <span class="w-4 h-4 shrink-0 rounded-full border-2 border-gray-600" aria-hidden="true"></span>
                        <span class="sr-only">{{ __('messages.progress_state_idle') }}:</span>
                    @endif
                    <span class="min-w-0">{{ $text }}</span>
                </li>
            @endforeach
        </ol>

        @if ($search->status === 'queued')
            <p class="text-xs text-gray-400">{{ __('messages.progress_queued') }}</p>
        @endif

        @if ($enrichment)
            <div class="h-1.5 rounded-full bg-gray-800 overflow-hidden" role="progressbar" aria-label="{{ __('messages.progress_enriching') }}"
                 aria-valuemin="0" aria-valuemax="{{ $enrichment['total'] }}" aria-valuenow="{{ $enrichment['done'] }}">
                <div class="h-full bg-emerald-500 transition-all" style="width: {{ $enrichment['total'] ? round($enrichment['done'] / $enrichment['total'] * 100) : 0 }}%"></div>
            </div>
        @endif
    @endif
</section>
