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
(function () {
    const mapboxToken = @json(config('services.mapbox.token'));
    const mapboxStyle = @json(config('services.mapbox.style', 'mapbox/streets-v12'));
    const loja  = @json($empresa);
    const leads = @json($allLeads);

    let map          = null;
    let markersLayer = null;

    function initMap() {
        if (map || !loja?.latitude || !loja?.longitude) return;

        map = L.map('map').setView([loja.latitude, loja.longitude], 12);

        if (mapboxToken) {
            L.tileLayer(`https://api.mapbox.com/styles/v1/${mapboxStyle}/tiles/{z}/{x}/{y}?access_token=${mapboxToken}`, {
                tileSize: 512, zoomOffset: -1, attribution: '© OpenStreetMap © Mapbox',
            }).addTo(map);
        } else {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap',
            }).addTo(map);
        }

        markersLayer = L.layerGroup().addTo(map);

        const storeIcon = L.divIcon({
            className: '',
            html: `<svg xmlns="http://www.w3.org/2000/svg" width="28" height="36" viewBox="0 0 28 36">
                <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="#10b981" stroke="#fff" stroke-width="1.5"/>
                <circle cx="14" cy="14" r="6" fill="#fff"/>
            </svg>`,
            iconSize: [28, 36],
            iconAnchor: [14, 36],
            popupAnchor: [0, -36],
        });

        L.marker([loja.latitude, loja.longitude], { icon: storeIcon })
            .bindPopup(`<b>${loja.nome}</b><br>Sua empresa`)
            .addTo(map);

        L.circle([loja.latitude, loja.longitude], {
            radius: (loja.raio_atendimento || 5) * 1000,
            color: '#10b981', fillColor: '#10b981', fillOpacity: 0.06, weight: 1,
        }).addTo(map);

        renderLeads();
        map.invalidateSize();
    }

    function renderLeads() {
        if (!map || !markersLayer) return;

        markersLayer.clearLayers();
        const bounds = [];

        leads.forEach(lead => {
            const lat = parseFloat(lead.latitude);
            const lng = parseFloat(lead.longitude);
            if (!lat || !lng) return;

            const score = lead.lead_score ?? 0;
            const color = score >= 80 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#6b7280';
            const phone = (lead.telefone || '').toString();
            const popup = [
                `<b>${lead.nome}</b>`,
                lead.endereco || '',
                lead.distancia_km ? `${parseFloat(lead.distancia_km).toFixed(1)} km` : '',
                phone,
            ].filter(Boolean).join('<br>');

            const leadIcon = L.divIcon({
                className: '',
                html: `<svg xmlns="http://www.w3.org/2000/svg" width="22" height="28" viewBox="0 0 28 36">
                    <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="${color}" stroke="#fff" stroke-width="1.5"/>
                    <circle cx="14" cy="14" r="5" fill="#fff" fill-opacity="0.9"/>
                </svg>`,
                iconSize: [22, 28],
                iconAnchor: [11, 28],
                popupAnchor: [0, -28],
            });

            L.marker([lat, lng], { icon: leadIcon }).bindPopup(popup).addTo(markersLayer);

            bounds.push([lat, lng]);
        });

        if (bounds.length > 0) {
            try { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 14 }); } catch (_) {}
        }

        map.invalidateSize();
    }

    document.addEventListener('livewire:initialized', () => {
        setTimeout(initMap, 50);
    });

    if (document.readyState !== 'loading') {
        setTimeout(initMap, 50);
    }
})();
</script>
@endpush
