<div class="space-y-6">

    {{-- Stats Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php
        $cards = [
            ['label' => __('messages.total_leads'), 'value' => $this->stats['total'], 'color' => 'blue', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => __('messages.nearby_leads'), 'value' => $this->stats['nearby'], 'color' => 'green', 'icon' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
            ['label' => __('messages.active_conversations'), 'value' => $this->conversasAtivas, 'color' => 'purple', 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
            ['label' => __('messages.response_rate'), 'value' => $this->taxaResposta . '%', 'color' => 'yellow', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
        ];
        @endphp

        @foreach ($cards as $card)
            <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">{{ $card['label'] }}</span>
                    <div class="w-8 h-8 bg-{{ $card['color'] }}-500/10 rounded-lg flex items-center justify-center">
                        <svg class="w-4 h-4 text-{{ $card['color'] }}-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $card['icon'] }}"/>
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-white">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Map --}}
        <div class="lg:col-span-2 bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-white">{{ __('messages.lead_map') }}</h3>
            </div>
            <div id="map" class="h-72 rounded-xl overflow-hidden bg-gray-800"></div>
        </div>

        {{-- AI Insights --}}
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-white">✨ {{ __('messages.ai_insights') }}</h3>
                <button wire:click="loadAIInsights"
                        wire:loading.attr="disabled"
                        class="text-xs px-3 py-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg transition-colors">
                    <span wire:loading.remove wire:target="loadAIInsights">{{ __('messages.generate') }}</span>
                    <span wire:loading wire:target="loadAIInsights">{{ __('messages.loading') }}...</span>
                </button>
            </div>
            @if ($this->aiInsights)
                <div class="space-y-3 text-sm text-gray-300">
                    @if (isset($this->aiInsights['mensagem']))
                        <div class="bg-gray-800 rounded-xl p-3">
                            <p class="text-xs text-green-400 font-medium mb-1">{{ __('messages.suggested_message') }}</p>
                            <p>{{ $this->aiInsights['mensagem'] }}</p>
                        </div>
                    @endif
                    @if (isset($this->aiInsights['horario']))
                        <div class="bg-gray-800 rounded-xl p-3">
                            <p class="text-xs text-blue-400 font-medium mb-1">{{ __('messages.best_time') }}</p>
                            <p>{{ $this->aiInsights['horario'] }}</p>
                        </div>
                    @endif
                </div>
            @else
                <p class="text-sm text-gray-500 text-center py-8">{{ __('messages.ai_insights_empty') }}</p>
            @endif
        </div>
    </div>

    {{-- Lead Modal --}}
    <div id="lead-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" id="lead-modal-backdrop"></div>
        <div class="relative bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md shadow-2xl">
            <div class="flex items-center justify-between p-5 border-b border-gray-800">
                <div class="flex items-center gap-3">
                    <div id="modal-score-badge" class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold"></div>
                    <div>
                        <h3 id="modal-nome" class="text-sm font-semibold text-white"></h3>
                        <p id="modal-cidade" class="text-xs text-gray-400"></p>
                    </div>
                </div>
                <button id="lead-modal-close" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-gray-800 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="p-5 space-y-3">
                <div id="modal-row-telefone" class="hidden items-center gap-3 text-sm">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span id="modal-telefone" class="text-gray-300"></span>
                </div>
                <div id="modal-row-endereco" class="hidden items-center gap-3 text-sm">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span id="modal-endereco" class="text-gray-300"></span>
                </div>
                <div id="modal-row-website" class="hidden items-center gap-3 text-sm">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                    <a id="modal-website" href="#" target="_blank" class="text-blue-400 hover:underline truncate"></a>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span id="modal-distancia" class="text-gray-300"></span>
                </div>
                <div id="modal-row-insights" class="hidden">
                    <div class="mt-1 bg-gray-800 rounded-xl p-3">
                        <p class="text-xs text-emerald-400 font-medium mb-1">IA Insights</p>
                        <p id="modal-insights-text" class="text-xs text-gray-300"></p>
                    </div>
                </div>
            </div>
            <div class="px-5 pb-5">
                <a id="modal-whatsapp-btn" href="#" target="_blank"
                   class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-medium rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                        <path d="M12 0C5.373 0 0 5.373 0 12c0 2.104.547 4.082 1.5 5.8L0 24l6.336-1.48A11.934 11.934 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.885 0-3.655-.493-5.19-1.357l-.374-.22-3.862.902.944-3.752-.242-.387A9.937 9.937 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                    </svg>
                    Abrir no WhatsApp
                </a>
            </div>
        </div>
    </div>

    {{-- Recent Leads & Conversations --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4">{{ __('messages.recent_leads') }}</h3>
            <div class="space-y-2">
                @forelse ($recentLeads as $lead)
                    <div class="flex items-center gap-3 py-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-violet-600 flex items-center justify-center text-xs font-bold flex-shrink-0">
                            {{ strtoupper(substr($lead->nome, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $lead->nome }}</p>
                            <p class="text-xs text-gray-400">{{ $lead->cidade }} · {{ $lead->distancia_km }} km</p>
                        </div>
                        <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full font-medium
                            {{ $lead->score_label === 'hot' ? 'bg-red-500/20 text-red-400' : ($lead->score_label === 'warm' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-700 text-gray-400') }}">
                            {{ $lead->lead_score }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">{{ __('messages.no_leads') }}</p>
                @endforelse
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-2xl p-5">
            <h3 class="text-sm font-semibold text-white mb-4">{{ __('messages.recent_conversations') }}</h3>
            <div class="space-y-2">
                @forelse ($recentConversations as $conv)
                    <div class="flex items-center gap-3 py-2">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-emerald-700 flex items-center justify-center text-xs font-bold flex-shrink-0">
                            {{ strtoupper(substr($conv->display_name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white truncate">{{ $conv->display_name }}</p>
                            <p class="text-xs text-gray-400 truncate">{{ $conv->last_message }}</p>
                        </div>
                        <span class="flex-shrink-0 text-xs text-gray-500">
                            {{ $conv->last_message_at?->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 text-center py-4">{{ __('messages.no_conversations') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('livewire:initialized', function () {
    const loja = @json($loja);
    const leads = @json($recentLeads);

    if (!loja.latitude) return;

    const map = L.map('map').setView([loja.latitude, loja.longitude], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // Store marker
    const storeIcon = L.divIcon({
        className: '',
        html: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
            <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="#22c55e" stroke="#fff" stroke-width="1.5"/>
            <circle cx="14" cy="14" r="6" fill="#fff"/>
        </svg>`,
        iconSize: [28, 36],
        iconAnchor: [14, 36],
    });

    L.marker([loja.latitude, loja.longitude], { icon: storeIcon })
        .bindPopup(`<b>${loja.nome}</b>`)
        .addTo(map);

    // Radius circle
    L.circle([loja.latitude, loja.longitude], {
        radius: loja.raio_atendimento * 1000,
        color: '#22c55e',
        fillColor: '#22c55e',
        fillOpacity: 0.05,
        weight: 1,
    }).addTo(map);

    // Modal helpers
    const modal     = document.getElementById('lead-modal');
    const backdrop  = document.getElementById('lead-modal-backdrop');
    const closeBtn  = document.getElementById('lead-modal-close');

    function showRow(id, value, display = 'flex') {
        const el = document.getElementById(id);
        if (value) {
            el.classList.remove('hidden');
            el.style.display = display;
        } else {
            el.classList.add('hidden');
            el.style.display = '';
        }
    }

    function openModal(lead) {
        const score = lead.lead_score ?? 0;
        const color = score >= 80 ? '#ef4444' : score >= 50 ? '#f59e0b' : '#6b7280';
        const bgColor = score >= 80 ? 'rgba(239,68,68,0.15)' : score >= 50 ? 'rgba(245,158,11,0.15)' : 'rgba(107,114,128,0.15)';

        const badge = document.getElementById('modal-score-badge');
        badge.textContent = score;
        badge.style.color = color;
        badge.style.backgroundColor = bgColor;

        document.getElementById('modal-nome').textContent = lead.nome ?? '';
        document.getElementById('modal-cidade').textContent = lead.cidade ?? '';
        document.getElementById('modal-distancia').textContent = lead.distancia_km
            ? `${parseFloat(lead.distancia_km).toFixed(1)} km de distância`
            : '';

        document.getElementById('modal-telefone').textContent = lead.telefone ?? '';
        showRow('modal-row-telefone', lead.telefone);

        document.getElementById('modal-endereco').textContent = lead.endereco ?? '';
        showRow('modal-row-endereco', lead.endereco);

        const websiteEl = document.getElementById('modal-website');
        websiteEl.textContent = lead.website ?? '';
        websiteEl.href = lead.website ?? '#';
        showRow('modal-row-website', lead.website);

        const insights = lead.ai_insights?.resumo ?? lead.ai_insights?.summary ?? null;
        document.getElementById('modal-insights-text').textContent = insights ?? '';
        showRow('modal-row-insights', insights, 'block');

        const phone = (lead.telefone ?? '').toString().replace(/\D/g, '');
        const waBtn = document.getElementById('modal-whatsapp-btn');
        if (phone) {
            waBtn.href = `https://wa.me/${phone}`;
            waBtn.classList.remove('hidden');
        } else {
            waBtn.classList.add('hidden');
        }

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.style.display = '';
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    // Lead markers
    leads.forEach(lead => {
        if (!lead.latitude) return;
        const score = lead.lead_score ?? 0;
        const color = score >= 80 ? '#ef4444' : score >= 50 ? '#f59e0b' : '#6b7280';
        const leadIcon = L.divIcon({
            className: '',
            html: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="28" viewBox="0 0 28 36" style="cursor:pointer">
                <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="${color}" stroke="#fff" stroke-width="1.5"/>
                <circle cx="14" cy="14" r="5" fill="#fff" fill-opacity="0.9"/>
            </svg>`,
            iconSize: [22, 28],
            iconAnchor: [11, 28],
        });
        L.marker([lead.latitude, lead.longitude], { icon: leadIcon })
            .on('click', () => openModal(lead))
            .addTo(map);
    });
});
</script>
@endpush
