import './bootstrap';
import { bootLeadsGeoMap } from './leads-geo-map';
import { prospectingMap } from './prospecting-map';

bootLeadsGeoMap();

// Vite modules run before DOMContentLoaded, when Livewire starts Alpine.
document.addEventListener('alpine:init', () => {
    window.Alpine.data('prospectingMap', prospectingMap);
});
