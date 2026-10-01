{{-- Saved searches: reopen the results or run the same search again. --}}
<ul class="divide-y divide-gray-800">
    @forelse ($history as $item)
        <li wire:key="history-{{ $compact ? 'menu' : 'panel' }}-{{ $item->id }}" class="px-4 py-3 space-y-2">
            <div class="min-w-0">
                <p class="text-sm text-gray-100 truncate" title="{{ $item->tipo_cliente }}">{{ $item->tipo_cliente }}</p>
                <p class="text-xs text-gray-400">
                    <time datetime="{{ $item->created_at->toIso8601String() }}">{{ $item->created_at->setTimezone($timezone)->format('d/m H:i') }}</time>
                    · {{ number_format((float) $item->radius_km, 1, ',', '') }} km
                    @if ($item->local_label) · <span class="break-words">{{ \Illuminate\Support\Str::limit($item->local_label, 40) }}</span> @endif
                    ·
                    @if ($item->status === 'failed')
                        <span class="text-red-300">{{ __('messages.search_status_failed') }}</span>
                    @elseif ($item->status === 'done')
                        {{ trans_choice('messages.search_results_count', $item->results_count, ['count' => $item->results_count]) }}
                    @else
                        <span class="text-amber-200">{{ __('messages.search_status_running') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($item->status !== 'failed')
                    <button type="button" wire:click="abrirBusca({{ $item->id }})" @if ($compact) @click="open = false" @endif
                            class="px-2.5 py-1 text-xs text-gray-100 bg-gray-800 hover:bg-gray-700 border border-gray-700 rounded-lg
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                        {{ __('messages.view_results') }}
                    </button>
                @endif
                <button type="button" wire:click="buscarDeNovo({{ $item->id }})" wire:confirm="{{ __('messages.search_again_confirm') }}" @if ($compact) @click="open = false" @endif
                        class="px-2.5 py-1 text-xs text-emerald-200 bg-emerald-600/15 hover:bg-emerald-600/25 border border-emerald-600/40 rounded-lg
                               focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400">
                    {{ __('messages.search_again') }}
                </button>
            </div>
        </li>
    @empty
        <li class="px-4 py-6 text-sm text-gray-400">{{ __('messages.no_searches_yet') }}</li>
    @endforelse
</ul>
