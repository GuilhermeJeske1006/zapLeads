@props(['status' => null, 'confidence' => null, 'origin' => null])

{{-- What enrichment found about reaching the lead on WhatsApp; the tooltip says where the number came from. --}}
@php
    $originLabel = $origin ? __('messages.contact_origin_' . $origin) : null;
    $dots = $confidence === null ? 0 : ($confidence >= 80 ? 3 : ($confidence >= 60 ? 2 : 1));
@endphp

@if (in_array($status, ['pending', 'running'], true))
    <span class="inline-flex items-center gap-1 mt-0.5 text-xs text-gray-400">
        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        {{ __('messages.enrichment_running') }}
    </span>
@elseif ($status === 'done' && $confidence !== null)
    @php $noWhatsApp = $confidence < \App\Models\LeadContact::MIN_WHATSAPP_CONFIDENCE; @endphp
    <span tabindex="0"
          class="group relative inline-flex items-center gap-1.5 mt-0.5 px-1.5 py-0.5 rounded text-xs border focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400
                 {{ $noWhatsApp ? 'bg-amber-500/15 text-amber-300 border-amber-500/30' : 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30' }}">
        @if ($noWhatsApp)
            {{ __('messages.no_whatsapp_call') }}
        @else
            {{ __('messages.contact_type_whatsapp') }}
            <span class="inline-flex gap-0.5" aria-hidden="true">
                @for ($i = 1; $i <= 3; $i++)
                    <span class="w-1.5 h-1.5 rounded-full {{ $i <= $dots ? 'bg-emerald-300' : 'bg-emerald-300/25' }}"></span>
                @endfor
            </span>
            <span class="tabular-nums">{{ $confidence }}</span>
        @endif

        <span class="sr-only">
            {{ $noWhatsApp ? __('messages.no_whatsapp_call_hint') : __('messages.whatsapp_confidence_hint') }}
            @if ($originLabel) {{ __('messages.contact_origin_label', ['origem' => $originLabel]) }} @endif
        </span>

        <span aria-hidden="true"
              class="pointer-events-none invisible opacity-0 group-hover:visible group-hover:opacity-100 group-focus:visible group-focus:opacity-100
                     absolute left-0 top-full z-30 mt-1 w-56 rounded-lg bg-gray-800 border border-gray-700 px-2.5 py-2 text-xs text-gray-200 shadow-lg transition-opacity">
            {{ $noWhatsApp ? __('messages.no_whatsapp_call_hint') : __('messages.whatsapp_confidence_hint') }}
            @if ($originLabel)
                <span class="block mt-1 text-gray-300">{{ __('messages.contact_origin_label', ['origem' => $originLabel]) }}</span>
            @endif
        </span>
    </span>
@endif
