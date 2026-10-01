@props(['lead'])

{{-- Why the lead has its score (scoring v2), plus the hook and pain the AI found. $lead is Lead::toArray(). --}}
@php
    $ai = $lead['ai_insights'] ?? [];
    $parts = $ai['score_breakdown'] ?? null;
    $labels = [
        'fit'             => __('messages.score_fit'),
        'contatabilidade' => __('messages.score_contact'),
        'dor'             => __('messages.score_pain'),
        'proximidade'     => __('messages.score_proximity'),
    ];
@endphp

<div class="bg-gray-800/60 rounded-xl p-4 space-y-3">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('messages.score_breakdown_title') }}</p>
        <x-lead-score :score="$lead['lead_score'] ?? 0" />
    </div>

    @if ($parts)
        <div class="space-y-2">
            @foreach (\App\Services\Scoring\LeadScoringService::WEIGHTS as $part => $weight)
                @php $value = $parts[$part] ?? null; @endphp
                <div>
                    <div class="flex items-baseline justify-between text-xs">
                        <span class="text-gray-300">
                            {{ $labels[$part] }}
                            <span class="text-gray-500">· {{ (int) round($weight * 100) }}%</span>
                            @if ($part === 'contatabilidade' && !empty($parts['contatabilidade_estimada']))
                                <span class="text-gray-500">· {{ __('messages.score_contact_estimated') }}</span>
                            @endif
                        </span>
                        <span class="tabular-nums {{ $value === null ? 'text-gray-500' : 'text-white font-medium' }}">
                            {{ $value === null ? __('messages.score_unknown') : $value }}
                        </span>
                    </div>
                    <div class="h-1.5 mt-1 rounded-full bg-gray-700 overflow-hidden" aria-hidden="true">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(0, min(100, (int) $value)) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-400">{{ __('messages.score_not_computed') }}</p>
    @endif

    @if (!empty($ai['gancho']))
        <div>
            <p class="text-xs text-gray-500">{{ __('messages.score_hook') }}</p>
            <p class="text-sm text-gray-200">{{ $ai['gancho'] }}</p>
        </div>
    @endif

    @if (!empty($ai['dor_provavel']))
        <div>
            <p class="text-xs text-gray-500">{{ __('messages.score_probable_pain') }}</p>
            <p class="text-sm text-gray-200">{{ $ai['dor_provavel'] }}</p>
        </div>
    @endif
</div>
