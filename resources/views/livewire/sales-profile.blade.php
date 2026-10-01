@php
    $input = 'w-full px-3 py-2.5 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-green-500 transition-colors';
    $fields = [
        ['model' => 'descricaoEmpresa',   'label' => 'sales_descricao',      'hint' => null,                     'rows' => 2, 'placeholder' => 'sales_descricao_placeholder'],
        ['model' => 'tipoClienteAlvo',    'label' => 'sales_cliente_ideal',  'hint' => null,                     'rows' => 2, 'placeholder' => 'sales_cliente_ideal_placeholder'],
        ['model' => 'ofertaPrincipal',    'label' => 'sales_oferta',         'hint' => null,                     'rows' => 0, 'placeholder' => 'sales_oferta_placeholder'],
        ['model' => 'problemaQueResolve', 'label' => 'sales_problema',       'hint' => null,                     'rows' => 2, 'placeholder' => 'sales_problema_placeholder'],
        ['model' => 'diferencial',        'label' => 'sales_diferencial',    'hint' => null,                     'rows' => 2, 'placeholder' => 'sales_diferencial_placeholder'],
        ['model' => 'provasSociais',      'label' => 'sales_provas',         'hint' => 'sales_provas_hint',      'rows' => 3, 'placeholder' => 'sales_provas_placeholder'],
        ['model' => 'ofertaDeEntrada',    'label' => 'sales_oferta_entrada', 'hint' => 'sales_oferta_entrada_hint', 'rows' => 0, 'placeholder' => 'sales_oferta_entrada_placeholder'],
        ['model' => 'segmentosExcluidos', 'label' => 'sales_excluidos',      'hint' => 'sales_excluidos_hint',   'rows' => 0, 'placeholder' => 'sales_excluidos_placeholder'],
    ];
@endphp

<form wire:submit="salvar" class="space-y-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach ($fields as $field)
            <div @class(['md:col-span-2' => $field['rows'] > 0])>
                <label for="sales-{{ $field['model'] }}" class="block text-xs font-medium text-gray-400 mb-1.5">
                    {{ __('messages.' . $field['label']) }}
                </label>
                @if ($field['rows'] > 0)
                    <textarea id="sales-{{ $field['model'] }}" wire:model="{{ $field['model'] }}" rows="{{ $field['rows'] }}"
                              placeholder="{{ __('messages.' . $field['placeholder']) }}"
                              class="{{ $input }} resize-none"></textarea>
                @else
                    <input id="sales-{{ $field['model'] }}" type="text" wire:model="{{ $field['model'] }}"
                           placeholder="{{ __('messages.' . $field['placeholder']) }}"
                           class="{{ $input }}">
                @endif
                @if ($field['hint'])
                    <p class="text-xs text-gray-500 mt-1">{{ __('messages.' . $field['hint']) }}</p>
                @endif
                @error($field['model']) <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <button type="submit" wire:loading.attr="disabled"
                class="px-6 py-2.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white font-medium rounded-xl transition-colors">
            {{ __('messages.save') }}
        </button>
    </div>
</form>
