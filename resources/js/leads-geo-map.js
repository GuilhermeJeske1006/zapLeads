function parseNumber(value) {
    const n = parseFloat(value);
    return Number.isFinite(n) ? n : null;
}

function getWireFrom(el) {
    const compEl = el?.closest?.('[wire\\:id]');
    const wireId = compEl?.getAttribute?.('wire:id');
    return wireId && window.Livewire ? window.Livewire.find(wireId) : null;
}

function initLeadsGeoMapOnce() {
    const panel = document.querySelector('[data-leads-geo-panel]');
    const mapEl = document.querySelector('[data-leads-geo-map]');
    if (!panel || !mapEl) return;
    if (!window.L) return;

    // Only init when visible to avoid Leaflet sizing issues.
    if (panel.offsetParent === null) return;

    const mapboxToken = mapEl.dataset.mapboxToken || '';
    const mapboxStyle = mapEl.dataset.mapboxStyle || 'mapbox/streets-v12';

    const centerLat = parseNumber(mapEl.dataset.centerLat) ?? -23.55052;
    const centerLng = parseNumber(mapEl.dataset.centerLng) ?? -46.633308;

    let map = mapEl.__leadsGeoMap || null;
    let marker = mapEl.__leadsGeoMarker || null;
    let circle = mapEl.__leadsGeoCircle || null;

    const wire = getWireFrom(mapEl);

    const radiusInput = document.querySelector('[data-leads-geo-radius]');
    const readRadiusKm = () => parseNumber(radiusInput?.value) ?? 5;

    const syncCircleRadius = () => {
        if (!circle) return;
        circle.setRadius(readRadiusKm() * 1000);
    };

    const applyCenter = (lat, lng) => {
        marker?.setLatLng([lat, lng]);
        circle?.setLatLng([lat, lng]);
        syncCircleRadius();

        const coordsLabel = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        const notify = (label) => wire?.call('setGeoCenter', lat, lng, label);

        if (mapboxToken) {
            fetch(`https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${mapboxToken}&limit=1`)
                .then(r => r.json())
                .then(data => notify(data.features?.[0]?.place_name ?? coordsLabel))
                .catch(() => notify(coordsLabel));
        } else {
            notify(coordsLabel);
        }
    };

    if (!map) {
        // If Livewire navigation swapped DOM, Leaflet might keep a stale _leaflet_id on the new node.
        try { delete mapEl._leaflet_id; } catch (_) {}

        map = window.L.map(mapEl).setView([centerLat, centerLng], 12);

        if (mapboxToken) {
            window.L.tileLayer(`https://api.mapbox.com/styles/v1/${mapboxStyle}/tiles/{z}/{x}/{y}?access_token=${mapboxToken}`, {
                tileSize: 512, zoomOffset: -1, attribution: '© OpenStreetMap © Mapbox'
            }).addTo(map);
        } else {
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap'
            }).addTo(map);
        }

        circle = window.L.circle([centerLat, centerLng], {
            radius: readRadiusKm() * 1000,
            color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.06, weight: 1,
        }).addTo(map);

        marker = window.L.marker([centerLat, centerLng], { draggable: true }).addTo(map);
        marker.bindPopup('Centro da busca');

        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            applyCenter(pos.lat, pos.lng);
        });

        map.on('click', (e) => {
            const { lat, lng } = e.latlng;
            applyCenter(lat, lng);
        });

        mapEl.__leadsGeoMap = map;
        mapEl.__leadsGeoMarker = marker;
        mapEl.__leadsGeoCircle = circle;

        if (radiusInput && !radiusInput.__leadsGeoHooked) {
            radiusInput.__leadsGeoHooked = true;
            radiusInput.addEventListener('input', syncCircleRadius);
            radiusInput.addEventListener('change', syncCircleRadius);
        }
    } else if (map._container !== mapEl) {
        // Container changed; drop cached instance and let next pass re-init.
        mapEl.__leadsGeoMap = null;
        mapEl.__leadsGeoMarker = null;
        mapEl.__leadsGeoCircle = null;
        return;
    }

    // Ensure sizing is correct.
    requestAnimationFrame(() => {
        try { map.invalidateSize(); } catch (_) {}
        syncCircleRadius();
    });

    const locateBtn = document.querySelector('[data-leads-geo-locate]');
    if (locateBtn && !locateBtn.__leadsGeoHooked) {
        locateBtn.__leadsGeoHooked = true;
        locateBtn.addEventListener('click', () => {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    map.setView([lat, lng], 13);
                    applyCenter(lat, lng);
                },
                () => {},
                { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
            );
        });
    }
}

export function bootLeadsGeoMap() {
    const attempt = () => initLeadsGeoMapOnce();

    // Initial.
    attempt();

    // Livewire lifecycle.
    document.addEventListener('livewire:initialized', attempt);
    document.addEventListener('livewire:navigated', attempt);

    if (window.Livewire) {
        try {
            window.Livewire.hook('message.processed', attempt);
            window.Livewire.on('leads-geo-open', () => setTimeout(attempt, 0));
            window.Livewire.on('leads-geo-reset', () => setTimeout(attempt, 0));
        } catch (_) {}
    }

    // Leaflet may load after Vite bundle; retry a few times.
    let tries = 0;
    const t = setInterval(() => {
        tries += 1;
        attempt();
        if (tries >= 20 || (document.querySelector('[data-leads-geo-map]') && window.L)) {
            clearInterval(t);
        }
    }, 250);
}

