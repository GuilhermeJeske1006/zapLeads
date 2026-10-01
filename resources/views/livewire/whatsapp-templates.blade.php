<div class="space-y-5">
    <p class="text-sm text-gray-400">{{ __('messages.templates_intro') }}</p>

    <form wire:submit="adicionar" class="flex flex-col sm:flex-row gap-2 sm:items-start">
        <div class="flex-1">
            <label for="template-content-sid" class="sr-only">ContentSid</label>
            <input id="template-content-sid" wire:model="contentSid" type="text" placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                   class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-sm font-mono
                          focus:outline-none focus:ring-2 focus:ring-green-500">
            @error('contentSid') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled"
                class="px-5 py-2.5 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-lg transition-colors
                       focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400 disabled:opacity-50">
            {{ __('messages.add_template') }}
        </button>
    </form>

    @foreach ($templates as $template)
        @php
            $statusClass = match ($template->status) {
                'approved' => 'bg-green-900/40 text-green-400 border-green-800',
                'rejected' => 'bg-red-900/40 text-red-400 border-red-800',
                default    => 'bg-yellow-900/40 text-yellow-300 border-yellow-800',
            };
        @endphp
        <div wire:key="template-{{ $template->id }}" class="border border-gray-800 rounded-xl p-4 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-medium text-white">{{ $template->nome }}</p>
                <span class="px-2 py-0.5 rounded text-xs border {{ $statusClass }}">{{ __('messages.template_status_' . $template->status) }}</span>
                @if ($template->categoria)
                    <span class="text-xs text-gray-400">{{ $template->categoria }}</span>
                @endif
                @if ($template->idioma)
                    <span class="text-xs text-gray-400">{{ $template->idioma }}</span>
                @endif
                <span class="text-xs text-gray-400 font-mono">{{ $template->content_sid }}</span>

                <div class="ml-auto flex items-center gap-3">
                    <button wire:click="alternarAtivo({{ $template->id }})"
                            class="text-xs {{ $template->ativo ? 'text-green-400' : 'text-gray-400' }} hover:text-white transition-colors">
                        {{ $template->ativo ? __('messages.active') : __('messages.inactive') }}
                    </button>
                    <button wire:click="sincronizar({{ $template->id }})" class="text-xs text-gray-400 hover:text-blue-400 transition-colors">
                        {{ __('messages.sync_status') }}
                    </button>
                    <button wire:click="remover({{ $template->id }})" wire:confirm="{{ __('messages.template_remove_confirm') }}"
                            class="text-xs text-gray-400 hover:text-red-400 transition-colors">
                        {{ __('messages.remove') }}
                    </button>
                </div>
            </div>

            @if ($template->corpo_preview)
                <p class="text-sm text-gray-200 whitespace-pre-line bg-gray-800/60 rounded-lg p-3">{{ $template->corpo_preview }}</p>
            @endif

            @if (!empty($template->variaveis))
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    @foreach ($template->variaveis as $placeholder => $field)
                        <label class="flex items-center gap-2 text-xs text-gray-400">
                            <code class="bg-gray-800 px-1.5 py-0.5 rounded text-gray-200">{{ str_repeat('{', 2) . $placeholder . str_repeat('}', 2) }}</code>
                            <select wire:change="mapear({{ $template->id }}, @js((string) $placeholder), $event.target.value)"
                                    class="flex-1 bg-gray-800 border {{ $field ? 'border-gray-700' : 'border-amber-600' }} text-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                <option value="">{{ __('messages.template_field_choose') }}</option>
                                @foreach (\App\Models\WhatsAppTemplate::FIELDS as $value => $label)
                                    <option value="{{ $value }}" @selected($field === $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
