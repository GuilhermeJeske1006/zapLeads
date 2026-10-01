{{-- Result filters: part of the step 1 form, applied live in step 2. --}}
@php $model = ($live ?? false) ? 'wire:model.live' : 'wire:model'; @endphp

<label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer">
    <input type="checkbox" {{ $model }}="soWhatsApp" class="w-4 h-4 rounded border-gray-600 bg-gray-800 accent-emerald-500">
    {{ __('messages.filter_probable_whatsapp') }}
</label>
<label class="inline-flex items-center gap-2 text-sm text-gray-200 cursor-pointer">
    <input type="checkbox" {{ $model }}="semSite" class="w-4 h-4 rounded border-gray-600 bg-gray-800 accent-emerald-500">
    {{ __('messages.filter_no_site') }}
</label>
<label class="inline-flex items-center gap-2 text-sm text-gray-200">
    {{ __('messages.filter_min_rating') }}
    <select {{ $model }}="notaMin"
            class="px-2 py-1 bg-gray-800 border border-gray-700 rounded-lg text-sm text-gray-100 focus:outline-none focus:border-emerald-500 focus-visible:ring-2 focus-visible:ring-emerald-500/40">
        <option value="">{{ __('messages.any') }}</option>
        @foreach (\App\Livewire\Prospecting\ProspectingWizard::RATINGS as $rating)
            <option value="{{ $rating }}">★ {{ str_replace('.', ',', $rating) }}+</option>
        @endforeach
    </select>
</label>
