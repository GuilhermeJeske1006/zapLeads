<div class="space-y-6">

    {{-- Form --}}
    {{-- <div class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-9 h-9 bg-violet-500/10 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                          d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                </svg>
            </div>
            <div>
                <h3 class="text-sm font-semibold text-white">Busca Inteligente de Clientes</h3>
                <p class="text-xs text-gray-500">IA analisa seus leads e retorna os que melhor combinam com seu negócio</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-5">
            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">
                    Descreva sua empresa
                    <span class="text-red-400 ml-0.5">*</span>
                </label>
                <textarea
                    wire:model="descricaoEmpresa"
                    rows="3"
                    placeholder="Ex: Loja de roupas femininas plus size, atendemos mulheres de 25 a 50 anos que buscam moda confortável e estilosa..."
                    class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                           focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30 transition-colors resize-none"
                ></textarea>
                @error('descricaoEmpresa')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-400 mb-1.5">
                    Tipo de cliente que você procura
                    <span class="text-red-400 ml-0.5">*</span>
                </label>
                <textarea
                    wire:model="tipoCliente"
                    rows="3"
                    placeholder="Ex: Mulheres entre 25 e 45 anos, que moram próximo à loja, interessadas em moda, renda média a alta..."
                    class="w-full px-3.5 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600
                           focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500/30 transition-colors resize-none"
                ></textarea>
                @error('tipoCliente')
                    <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <button
            wire:click="buscar"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-60 cursor-not-allowed"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-500 text-white text-sm font-medium rounded-xl transition-colors">
            <span wire:loading.remove wire:target="buscar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </span>
            <span wire:loading wire:target="buscar">
                <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </span>
            <span wire:loading.remove wire:target="buscar">Buscar clientes ideais</span>
            <span wire:loading wire:target="buscar">Analisando leads...</span>
        </button>
    </div> --}}

    {{-- Results --}}
    @if ($buscaFeita)
        <div class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <h3 class="text-sm font-semibold text-white">Resultados</h3>
                    <span class="text-xs px-2 py-0.5 bg-violet-500/20 text-violet-400 rounded-full font-medium">
                        {{ count($resultados) }} {{ count($resultados) === 1 ? 'lead encontrado' : 'leads encontrados' }}
                    </span>
                </div>
            </div>

            @if (count($resultados) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase tracking-wide border-b border-gray-800">
                                <th class="px-5 py-3 text-left">Cliente</th>
                                <th class="px-5 py-3 text-left">Telefone</th>
                                <th class="px-5 py-3 text-left">Cidade</th>
                                <th class="px-5 py-3 text-left">Distância</th>
                                <th class="px-5 py-3 text-left">Score Lead</th>
                                <th class="px-5 py-3 text-left">Match IA</th>
                                <th class="px-5 py-3 text-left">Por quê?</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/50">
                            @foreach ($resultados as $lead)
                                @php
                                    $matchScore = $lead['match_score'] ?? 0;
                                    $matchColor = $matchScore >= 80 ? 'text-green-400 bg-green-500/20' :
                                                 ($matchScore >= 50 ? 'text-yellow-400 bg-yellow-500/20' : 'text-gray-400 bg-gray-700');
                                    $leadScore  = $lead['lead_score'] ?? 0;
                                    $leadColor  = $leadScore >= 80 ? 'text-red-400 bg-red-500/20' :
                                                 ($leadScore >= 50 ? 'text-yellow-400 bg-yellow-500/20' : 'text-gray-400 bg-gray-700');
                                @endphp
                                <tr class="hover:bg-gray-800/30 transition-colors">
                                    <td class="px-5 py-3 font-medium text-white">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-violet-500 to-blue-600
                                                        flex items-center justify-center text-xs font-bold flex-shrink-0">
                                                {{ strtoupper(substr($lead['nome'] ?? '?', 0, 1)) }}
                                            </div>
                                            {{ $lead['nome'] ?? '—' }}
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 font-mono text-xs">
                                        {{ $lead['telefone'] ?? '—' }}
                                    </td>
                                    <td class="px-5 py-3 text-gray-300">{{ $lead['cidade'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-gray-300">
                                        {{ isset($lead['distancia_km']) ? number_format($lead['distancia_km'], 1) . ' km' : '—' }}
                                        @if (!empty($lead['is_nearby']))
                                            <span class="ml-1 text-xs text-green-400">✓</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $leadColor }}">
                                            {{ $leadScore >= 80 ? '🔥' : ($leadScore >= 50 ? '🌡️' : '❄️') }}
                                            {{ $leadScore }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold {{ $matchColor }}">
                                            ✦ {{ $matchScore }}%
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-xs max-w-xs">
                                        {{ $lead['match_motivo'] ?? '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-16 text-center">
                    <p class="text-gray-500 text-sm">Nenhum lead compatível encontrado com esse perfil.</p>
                    <p class="text-gray-600 text-xs mt-1">Tente ajustar a descrição da empresa ou o tipo de cliente.</p>
                </div>
            @endif
        </div>
    @endif

</div>
