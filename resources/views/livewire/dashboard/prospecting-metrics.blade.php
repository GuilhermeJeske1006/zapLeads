@php
    $percent = fn (?float $rate) => $rate === null ? '—' : round($rate * 100) . '%';
    $selectClass = 'px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40';
    $topo = max(1, $funil['buscados']);
@endphp

<div class="space-y-6">
    {{-- Funnel --}}
    <section aria-labelledby="funnel-title" class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
        <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
            <div>
                <h3 id="funnel-title" class="text-sm font-semibold text-white">{{ __('messages.funnel_title') }}</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ __('messages.funnel_subtitle') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <div>
                    <label for="funnel-periodo" class="block text-xs text-gray-400 mb-1">{{ __('messages.funnel_period') }}</label>
                    <select id="funnel-periodo" wire:model.live="periodo" @disabled($buscaId !== '') class="{{ $selectClass }} disabled:opacity-50">
                        @foreach (\App\Livewire\Dashboard\ProspectingMetrics::PERIODS as $p)
                            <option value="{{ $p }}">{{ __('messages.funnel_period_' . $p) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="funnel-busca" class="block text-xs text-gray-400 mb-1">{{ __('messages.funnel_search') }}</label>
                    <select id="funnel-busca" wire:model.live="buscaId" class="{{ $selectClass }} max-w-64">
                        <option value="">{{ __('messages.funnel_all_searches') }}</option>
                        @foreach ($searches as $s)
                            <option value="{{ $s->id }}">{{ $s->created_at->setTimezone($timezone)->format('d/m') }} · {{ \Illuminate\Support\Str::limit($s->tipo_cliente, 40) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($funil['buscados'] === 0)
            <p class="text-sm text-gray-400 text-center py-6">
                {{ __('messages.funnel_empty') }}
                <a href="{{ route('prospeccao.index') }}" class="text-emerald-400 hover:text-emerald-300 underline focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 rounded">{{ __('messages.funnel_empty_cta') }}</a>
            </p>
        @else
            <ol class="space-y-2.5">
                @foreach (\App\Services\Metrics\FunnelReport::STAGES as $i => $stage)
                    @php
                        $count = $funil[$stage];
                        $previous = $i > 0 ? $funil[\App\Services\Metrics\FunnelReport::STAGES[$i - 1]] : null;
                    @endphp
                    <li class="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 items-center sm:grid-cols-[minmax(7rem,10rem)_1fr_auto]">
                        <span class="text-sm text-gray-300">{{ __('messages.funnel_stage_' . $stage) }}</span>
                        <div class="col-span-2 row-start-2 h-4 sm:h-6 sm:col-span-1 sm:row-start-auto bg-gray-800 rounded-md overflow-hidden" aria-hidden="true">
                            <div class="h-full bg-emerald-500/70 rounded-md" style="width: {{ max($count > 0 ? 2 : 0, round($count / $topo * 100)) }}%"></div>
                        </div>
                        <span class="text-sm tabular-nums text-white text-right sm:min-w-24">
                            {{ $count }}
                            @if ($previous !== null)
                                <span class="text-xs text-gray-400">· {{ $previous > 0 ? $percent($count / $previous) : '—' }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ol>
            <p class="text-xs text-gray-400 mt-4">{{ __('messages.funnel_hint') }}</p>
        @endif
    </section>

    {{-- A/B --}}
    <section aria-labelledby="ab-title" class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
        <h3 id="ab-title" class="text-sm font-semibold text-white">{{ __('messages.ab_title') }}</h3>
        <p class="text-xs text-gray-400 mt-0.5 mb-4">{{ __('messages.ab_subtitle', ['min' => \App\Services\Prospecting\AngleExperiment::MIN_SENDS, 'explore' => \App\Services\Prospecting\AngleExperiment::EXPLORATION]) }}</p>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('messages.ab_by_angle') }}</caption>
                    <thead>
                        <tr class="text-xs text-gray-400 text-left border-b border-gray-800">
                            <th scope="col" class="py-2 pr-3 font-medium">{{ __('messages.outreach_angles') }}</th>
                            <th scope="col" class="py-2 pr-3 font-medium text-right">{{ __('messages.ab_sent') }}</th>
                            <th scope="col" class="py-2 pr-3 font-medium text-right">{{ __('messages.ab_replies') }}</th>
                            <th scope="col" class="py-2 font-medium text-right">{{ __('messages.ab_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60">
                        @foreach ($angles as $angle => $row)
                            <tr>
                                <th scope="row" class="py-2 pr-3 font-normal text-gray-200 text-left">
                                    {{ __('messages.outreach_angle_' . $angle) }}
                                    @if ($winner === $angle)
                                        <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-xs bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">{{ __('messages.ab_winner') }}</span>
                                    @elseif ($winner === null && $row['envios'] < \App\Services\Prospecting\AngleExperiment::MIN_SENDS)
                                        <span class="block text-xs text-gray-400">{{ __('messages.ab_collecting', ['sent' => $row['envios'], 'min' => \App\Services\Prospecting\AngleExperiment::MIN_SENDS]) }}</span>
                                    @endif
                                </th>
                                <td class="py-2 pr-3 text-right tabular-nums text-gray-300">{{ $row['envios'] }}</td>
                                <td class="py-2 pr-3 text-right tabular-nums text-gray-300">{{ $row['respostas'] }}</td>
                                <td class="py-2 text-right tabular-nums text-white">{{ $percent($row['taxa']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="space-y-5">
                <div>
                    <h4 class="text-xs font-medium text-gray-300 mb-2">{{ __('messages.ab_by_channel') }}</h4>
                    @forelse ($canais as $row)
                        <p class="flex justify-between gap-3 text-sm py-1">
                            <span class="text-gray-300">{{ __('messages.ab_channel_' . $row['canal']) }}</span>
                            <span class="tabular-nums text-white">{{ $percent($row['taxa']) }} <span class="text-xs text-gray-400">({{ $row['respostas'] }}/{{ $row['envios'] }})</span></span>
                        </p>
                    @empty
                        <p class="text-xs text-gray-400">{{ __('messages.ab_no_sends') }}</p>
                    @endforelse
                </div>
                <div>
                    <h4 class="text-xs font-medium text-gray-300 mb-2">{{ __('messages.ab_by_template') }}</h4>
                    @forelse ($templates as $row)
                        <p class="flex justify-between gap-3 text-sm py-1">
                            <span class="text-gray-300 truncate">{{ $row['template'] }}</span>
                            <span class="tabular-nums text-white shrink-0">{{ $percent($row['taxa']) }} <span class="text-xs text-gray-400">({{ $row['respostas'] }}/{{ $row['envios'] }})</span></span>
                        </p>
                    @empty
                        <p class="text-xs text-gray-400">{{ __('messages.ab_no_templates') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
</div>
