@props(['status' => 'novo'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-0.5 rounded-full text-xs border ' . \App\Models\Lead::statusClasses($status)]) }}>
    {{ \App\Models\Lead::statusLabel($status) }}
</span>
