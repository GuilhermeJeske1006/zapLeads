@props(['score' => null])

@php
    $score = (int) ($score ?? 0);
    $color = $score >= 80 ? 'text-green-400 bg-green-500/20'
           : ($score >= 50 ? 'text-yellow-400 bg-yellow-500/20' : 'text-gray-400 bg-gray-700/50');
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold tabular-nums {$color}"]) }}
      title="{{ __('messages.score_hint') }}">
    {{ $score }}
</span>
