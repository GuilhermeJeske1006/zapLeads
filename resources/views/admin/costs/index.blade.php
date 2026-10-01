<x-app-layout>
    <x-slot name="title">{{ __('messages.costs_title') }}</x-slot>

    @php
        $usd = fn (?float $v) => $v === null ? '—' : 'US$ ' . number_format($v, $v > 0 && $v < 1 ? 4 : 2, ',', '.');
        $percent = fn (?float $rate) => $rate === null ? '—' : round($rate * 100) . '%';
        $num = fn (int $v) => number_format($v, 0, ',', '.');
        $origem = fn (?string $o) => __('messages.cost_origin_' . ($o ?: 'outros'));
        $th = 'px-4 py-2.5 font-medium';
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm text-gray-300 max-w-3xl">{{ __('messages.costs_intro') }}</p>
                <p class="text-xs text-gray-400 mt-1 max-w-3xl">{{ __('messages.costs_qualified_hint', ['fit' => config('costs.qualified_min_fit')]) }}</p>
            </div>
            <form method="GET" action="{{ route('admin.costs.index') }}" class="flex items-end gap-2">
                <div>
                    <label for="costs-dias" class="block text-xs text-gray-400 mb-1">{{ __('messages.funnel_period') }}</label>
                    <select id="costs-dias" name="dias" onchange="this.form.submit()"
                            class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-200 focus:outline-none focus:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40">
                        @foreach (\App\Http\Controllers\Admin\CostsController::PERIODS as $p)
                            <option value="{{ $p }}" @selected($p === $dias)>{{ __('messages.funnel_period_' . $p) }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button class="px-3 py-2 bg-gray-700 text-sm text-white rounded-lg">{{ __('messages.apply') }}</button></noscript>
            </form>
        </div>

        {{-- Totals --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <h2 class="text-xs text-gray-400 font-medium">{{ __('messages.costs_total') }}</h2>
                <p class="text-3xl font-bold text-white mt-2 tabular-nums">{{ $usd($totais['total_usd']) }}</p>
                <ul class="mt-4 space-y-1.5 text-sm">
                    @foreach ($porOrigem as $row)
                        <li class="flex justify-between gap-3"><span class="text-gray-300">{{ $origem($row['origem']) }}</span><span class="tabular-nums text-white">{{ $usd($row['usd']) }}</span></li>
                    @endforeach
                </ul>
            </section>

            <section class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl overflow-x-auto">
                <table class="w-full text-sm">
                    <caption class="text-left px-4 pt-4 pb-2 text-xs text-gray-400 font-medium">{{ __('messages.costs_by_service') }}</caption>
                    <thead>
                        <tr class="text-xs text-gray-400 text-left border-b border-gray-800">
                            <th scope="col" class="{{ $th }}">{{ __('messages.costs_service') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_calls') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_tokens') }}</th>
                            <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/60">
                        @forelse ($totais['itens'] as $item)
                            <tr>
                                <th scope="row" class="px-4 py-2 font-normal text-left">
                                    <span class="text-gray-200">{{ __('messages.cost_service_' . $item['servico']) }}</span>
                                    <span class="block text-xs text-gray-400 font-mono">{{ $item['sku'] }}</span>
                                </th>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-300">
                                    {{ $num($item['chamadas']) }}
                                    @if ($item['web_searches'] > 0)
                                        <span class="block text-xs text-gray-400">{{ __('messages.costs_web_searches', ['count' => $num($item['web_searches'])]) }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-300">
                                    @if ($item['servico'] === 'anthropic')
                                        {{ $num($item['input_tokens'] + $item['cache_read_tokens'] + $item['cache_write_tokens']) }} / {{ $num($item['output_tokens']) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-white">{{ $usd($item['usd']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">{{ __('messages.costs_empty') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <p class="px-4 pb-3 text-xs text-gray-400">{{ __('messages.costs_tokens_hint') }}</p>
            </section>
        </div>

        {{-- Per empresa --}}
        <section class="bg-gray-900 border border-gray-800 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="text-left px-4 pt-4 pb-2 text-sm font-semibold text-white">{{ __('messages.costs_by_empresa') }}</caption>
                <thead>
                    <tr class="text-xs text-gray-400 text-left border-b border-gray-800">
                        <th scope="col" class="{{ $th }}">{{ __('messages.costs_empresa') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_searches') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_prospecting') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_total') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_qualified') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_per_qualified') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse ($empresas as $row)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal text-left text-gray-200">{{ $row['empresa'] }}</th>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $row['buscas'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $usd($row['prospeccao']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $usd($row['total']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $row['qualificados'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-white">{{ $usd($row['por_qualificado']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('messages.costs_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Per search --}}
        <section class="bg-gray-900 border border-gray-800 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="text-left px-4 pt-4 pb-2 text-sm font-semibold text-white">{{ __('messages.costs_by_search') }}</caption>
                <thead>
                    <tr class="text-xs text-gray-400 text-left border-b border-gray-800">
                        <th scope="col" class="{{ $th }}">{{ __('messages.costs_search') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_results') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_qualified') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cost') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_per_qualified') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse ($buscas as $row)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal text-left">
                                <span class="text-gray-200">{{ $row['empresa'] }}</span>
                                <span class="block text-xs text-gray-400">#{{ $row['id'] }} · {{ $row['quando']->format('d/m H:i') }} · {{ \Illuminate\Support\Str::limit($row['cliente'], 50) }}</span>
                            </th>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $row['resultados'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $row['qualificados'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">
                                {{ $usd($row['custo']) }}
                                @if ($row['parcial'])
                                    <span class="block text-xs text-amber-300">{{ __('messages.costs_partial') }}</span>
                                @elseif ($row['por_servico'])
                                    <span class="block text-xs text-gray-400">
                                        {{ collect($row['por_servico'])->map(fn ($v, $s) => __('messages.cost_service_' . $s) . ' ' . $usd($v))->join(' · ') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-white">{{ $usd($row['por_qualificado']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">{{ __('messages.costs_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Prompt caching --}}
        <section class="bg-gray-900 border border-gray-800 rounded-2xl overflow-x-auto">
            <table class="w-full text-sm">
                <caption class="text-left px-4 pt-4 pb-1 text-sm font-semibold text-white">{{ __('messages.costs_cache_title') }}</caption>
                <thead>
                    <tr class="text-xs text-gray-400 text-left border-b border-gray-800">
                        <th scope="col" class="{{ $th }}">{{ __('messages.costs_model') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_calls') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cache_input') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cache_read') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cache_written') }}</th>
                        <th scope="col" class="{{ $th }} text-right">{{ __('messages.costs_cache_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse ($cache as $row)
                        <tr>
                            <th scope="row" class="px-4 py-2 font-normal text-left text-gray-200 font-mono text-xs">{{ $row['modelo'] }}</th>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $num($row['chamadas']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $num($row['entrada']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $num($row['lido']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-300">{{ $num($row['gravado']) }}</td>
                            <td class="px-4 py-2 text-right tabular-nums text-white">{{ $percent($row['taxa']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">{{ __('messages.costs_empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="px-4 pb-3 text-xs text-gray-400">{{ __('messages.costs_cache_hint') }}</p>
        </section>
    </div>
</x-app-layout>
