// Leaflet map of the Prospecção wizard, as an Alpine component.
//   mode "picker":  step 1, a click picks the search center (sent to Livewire with a reverse-geocoded label).
//   mode "results": step 2, the search's leads as pins colored by score; a pin opens the lead's dossier.
// Leaflet itself is loaded by the layout (window.L).

const PIN = (color, size = 22) => `
    <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${Math.round(size * 1.28)}" viewBox="0 0 28 36" aria-hidden="true">
        <path d="M14 0C6.27 0 0 6.27 0 14c0 9.625 14 22 14 22s14-12.375 14-22C28 6.27 21.73 0 14 0z" fill="${color}" stroke="#fff" stroke-width="1.5"/>
        <circle cx="14" cy="14" r="5" fill="#fff" fill-opacity="0.9"/>
    </svg>`;

const scoreColor = (score) => (score >= 80 ? '#22c55e' : score >= 50 ? '#f59e0b' : '#9ca3af');

const escapeHtml = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function icon(color, size) {
    const height = Math.round(size * 1.28);
    return window.L.divIcon({ className: '', html: PIN(color, size), iconSize: [size, height], iconAnchor: [size / 2, height], popupAnchor: [0, -height] });
}

export function prospectingMap(config) {
    return {
        map: null,
        pins: null,
        circle: null,
        picked: null,
        resizeObserver: null,

        init() {
            if (!window.L) return;

            const center = config.center ?? config.company ?? { lat: -23.55052, lng: -46.633308 };
            this.map = window.L.map(this.$refs.canvas, { scrollWheelZoom: false }).setView([center.lat, center.lng], 13);

            const tiles = config.token
                ? window.L.tileLayer(`https://api.mapbox.com/styles/v1/${config.style}/tiles/{z}/{x}/{y}?access_token=${config.token}`, {
                    tileSize: 512, zoomOffset: -1, attribution: '© OpenStreetMap © Mapbox',
                })
                : window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' });
            tiles.addTo(this.map);

            this.pins = window.L.layerGroup().addTo(this.map);

            if (config.company) {
                window.L.marker([config.company.lat, config.company.lng], { icon: icon('#10b981', 28), keyboard: false })
                    .bindPopup(escapeHtml(config.labels.company))
                    .addTo(this.map);
            }

            this.circle = window.L.circle([center.lat, center.lng], {
                radius: (config.radiusKm || 5) * 1000, color: '#10b981', fillColor: '#10b981', fillOpacity: 0.06, weight: 1,
            }).addTo(this.map);

            if (config.mode === 'picker') {
                if (config.picked) this.pick(config.picked.lat, config.picked.lng, false);
                this.map.on('click', (e) => this.pick(e.latlng.lat, e.latlng.lng, true));
                this.$watch('$wire.raioBuscaKm', (km) => this.circle.setRadius((parseFloat(km) || 5) * 1000));
            } else {
                this.render(config);
            }

            // Leaflet measures its box once: re-measure when the map is shown, collapsed or resized.
            this.resizeObserver = new ResizeObserver(() => this.map?.invalidateSize());
            this.resizeObserver.observe(this.$refs.canvas);
        },

        destroy() {
            this.resizeObserver?.disconnect();
            this.map?.remove();
            this.map = null;
        },

        // Livewire: prospecting-map:results
        render({ center, radiusKm, markers }) {
            if (!this.map || config.mode !== 'results') return;

            if (center?.lat && center?.lng) {
                this.circle.setLatLng([center.lat, center.lng]);
                this.circle.setRadius((radiusKm || 5) * 1000);
            }

            this.pins.clearLayers();
            const bounds = [];

            (markers || []).forEach((lead) => {
                const marker = window.L.marker([lead.lat, lead.lng], { icon: icon(scoreColor(lead.score), 22), title: `${lead.nome} (${lead.score})` })
                    .bindTooltip(`${escapeHtml(lead.nome)} · ${lead.score}`)
                    .on('click', () => window.Livewire.dispatch('open-lead-dossier', { id: lead.id }))
                    .addTo(this.pins);
                bounds.push(marker.getLatLng());
            });

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
            } else if (center?.lat && center?.lng) {
                this.map.setView([center.lat, center.lng], 13);
            }
        },

        pick(lat, lng, notify) {
            if (this.picked) {
                this.picked.setLatLng([lat, lng]);
            } else {
                this.picked = window.L.marker([lat, lng], { icon: icon('#f59e0b', 28), draggable: true }).addTo(this.map);
                this.picked.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.pick(pos.lat, pos.lng, true);
                });
            }
            this.circle.setLatLng([lat, lng]);

            if (!notify) return;

            const send = (label) => window.Livewire.dispatch('prospecting-map:location', { lat, lng, label });
            if (!config.token) return send('');

            fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${config.token}&limit=1`)
                .then((r) => r.json())
                .then((data) => send(data.features?.[0]?.place_name ?? ''))
                .catch(() => send(''));
        },
    };
}
