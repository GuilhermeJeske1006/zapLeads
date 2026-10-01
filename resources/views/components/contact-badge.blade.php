@props(['status' => null, 'confidence' => null])

{{-- What enrichment found about reaching the lead on WhatsApp. --}}
@if (in_array($status, ['pending', 'running'], true))
    <span class="inline-flex items-center gap-1 mt-0.5 text-xs text-gray-400">
        <svg class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        {{ __('messages.enrichment_running') }}
    </span>
@elseif ($status === 'done' && $confidence !== null && $confidence < \App\Models\LeadContact::MIN_WHATSAPP_CONFIDENCE)
    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-xs bg-amber-500/15 text-amber-300 border border-amber-500/30"
          title="{{ __('messages.no_whatsapp_call_hint') }}">
        {{ __('messages.no_whatsapp_call') }}
    </span>
@elseif ($status === 'done' && $confidence !== null)
    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-xs bg-emerald-500/15 text-emerald-300 border border-emerald-500/30"
          title="{{ __('messages.whatsapp_confidence_hint') }}">
        {{ __('messages.whatsapp_confidence', ['value' => $confidence]) }}
    </span>
@endif
