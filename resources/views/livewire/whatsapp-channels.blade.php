<div class="space-y-5">

    {{-- Flash messages --}}
    @if (session('success'))
        <div class="bg-green-900/50 border border-green-700 text-green-300 rounded-lg px-4 py-3 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if (session('warning'))
        <div class="bg-yellow-900/50 border border-yellow-700 text-yellow-300 rounded-lg px-4 py-3 text-sm flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 4a8 8 0 100 16A8 8 0 0012 4z"/></svg>
            {{ session('warning') }}
        </div>
    @endif

    {{-- ── TELA DE SELEÇÃO ── --}}
    @if ($mode === null)
        <p class="text-sm text-gray-400">
            Para enviar e receber mensagens pelo WhatsApp, você precisa conectar ao menos um número.
            Existem duas formas de fazer isso — escolha a que se encaixa no seu momento:
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

            {{-- Card: Número de Teste --}}
            <div class="flex flex-col border border-gray-700 rounded-xl p-5 bg-gray-800/40 hover:border-gray-600 transition-colors">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xl">🧪</span>
                    <h3 class="font-semibold text-white">Número de Teste (Sandbox)</h3>
                </div>
                <p class="text-sm text-gray-400 mb-4 flex-1">
                    Use o número compartilhado da Twilio para testar o envio e recebimento de mensagens
                    sem precisar configurar nada no Meta.
                </p>

                <div class="space-y-1.5 mb-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">O que você precisa</p>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Conta Twilio (gratuita para testes)
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Entrar no sandbox enviando <code class="bg-gray-900 px-1 rounded text-xs">join &lt;palavra&gt;</code> para o número da Twilio
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-400">
                        <svg class="w-4 h-4 text-red-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        Não é seu número — não usar em produção
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500 mb-5 bg-gray-900/60 rounded-lg px-3 py-2">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Pronto em minutos — ideal para desenvolvimento e testes
                </div>

                <button wire:click="selectMode('manual')"
                    class="w-full px-4 py-2.5 bg-gray-700 hover:bg-gray-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Usar número de teste
                </button>
            </div>

            {{-- Card: Meu Número (BYOP) --}}
            <div class="flex flex-col border border-green-800/60 rounded-xl p-5 bg-green-950/20 hover:border-green-700 transition-colors">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xl">📱</span>
                    <h3 class="font-semibold text-white">Meu Número (Produção)</h3>
                    <span class="ml-auto text-xs bg-green-900/60 text-green-400 border border-green-800 px-2 py-0.5 rounded-full">Recomendado</span>
                </div>
                <p class="text-sm text-gray-400 mb-4 flex-1">
                    Registre seu próprio número WhatsApp Business para uso profissional.
                    As mensagens chegam no seu número, com o nome da sua empresa.
                </p>

                <div class="space-y-1.5 mb-5">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">O que você precisa</p>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Conta <strong>Meta Business Manager</strong> verificada</span>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>WhatsApp Business Account (WABA) criada no Meta — o <strong>WABA ID</strong> será pedido no formulário</span>
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Conta Twilio conectada ao Meta Business Manager
                    </div>
                    <div class="flex items-start gap-2 text-sm text-gray-300">
                        <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Número de telefone ainda não cadastrado no WhatsApp
                    </div>
                </div>

                <div class="flex items-center gap-2 text-xs text-gray-500 mb-5 bg-gray-900/60 rounded-lg px-3 py-2">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Aprovação pelo Meta pode levar de algumas horas a 7 dias
                </div>

                <button wire:click="selectMode('byop')"
                    class="w-full px-4 py-2.5 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-lg transition-colors">
                    Registrar meu número
                </button>
            </div>
        </div>
    @endif

    {{-- ── MODO MANUAL (SANDBOX / TWILIO JÁ CONFIGURADO) ── --}}
    @if ($mode === 'manual')
        <div>
            <button wire:click="backToSelection" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-300 transition-colors mb-5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </button>

            <div class="flex items-center gap-2 mb-1">
                <span class="text-lg">🧪</span>
                <h3 class="font-semibold text-white">{{ $editingId ? 'Editar canal' : 'Adicionar número de teste' }}</h3>
            </div>

            @if (!$editingId)
                <div class="bg-blue-950/40 border border-blue-800/60 rounded-lg px-4 py-3 text-xs text-blue-300 space-y-2 mb-5 mt-3">
                    <p class="font-semibold text-blue-200">Como entrar no sandbox da Twilio:</p>
                    <ol class="list-decimal list-inside space-y-1 text-blue-300">
                        <li>Acesse o <a href="https://console.twilio.com/us1/develop/sms/try-it-out/whatsapp-learn" target="_blank" class="underline hover:text-blue-100">Console Twilio → WhatsApp Sandbox</a></li>
                        <li>Anote o número do sandbox (ex: <code class="bg-blue-900/40 px-1 rounded">+1 415 523 8886</code>)</li>
                        <li>No seu WhatsApp, envie a mensagem indicada (ex: <code class="bg-blue-900/40 px-1 rounded">join &lt;palavra&gt;</code>) para esse número</li>
                        <li>Após o WhatsApp confirmar, cole o número abaixo no formato <code class="bg-blue-900/40 px-1 rounded">whatsapp:+14155238886</code></li>
                    </ol>
                    <p class="text-blue-400">
                        O número sandbox do seu plano Twilio pode ser diferente do exemplo acima.
                        Verifique no console.
                    </p>
                </div>
            @endif

            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">
                            Nome do canal
                            <span class="text-gray-600 font-normal ml-1">— para identificar internamente</span>
                        </label>
                        <input wire:model="nome" type="text" placeholder="Ex: Sandbox de Testes"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
                        @error('nome') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-400 mb-1.5">
                            Número Twilio
                            <span class="text-gray-600 font-normal ml-1">— com prefixo <code class="bg-gray-700 px-1 rounded">whatsapp:</code></span>
                        </label>
                        <input wire:model="numero" type="text" placeholder="whatsapp:+14155238886"
                            class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
                        @error('numero') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div class="max-w-xs">
                    <label for="channel-daily-limit" class="block text-xs font-medium text-gray-400 mb-1.5">{{ __('messages.daily_prospecting_limit') }}</label>
                    <input id="channel-daily-limit" wire:model="limiteDiario" type="number" min="1" max="1000"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 transition-colors">
                    <p class="text-xs text-gray-400 mt-1">{{ __('messages.daily_prospecting_limit_hint') }}</p>
                    @error('limiteDiario') <span class="text-red-400 text-xs mt-1 block">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center gap-5">
                    <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                        <input wire:model="isDefault" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-green-500 focus:ring-green-500">
                        Canal padrão
                        <span class="text-xs text-gray-600">(usado quando nenhum outro for especificado)</span>
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-400 cursor-pointer">
                        <input wire:model="ativo" type="checkbox" class="rounded border-gray-600 bg-gray-800 text-green-500 focus:ring-green-500">
                        Ativo
                    </label>
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="px-5 py-2.5 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-lg transition-colors">
                        {{ $editingId ? 'Salvar alterações' : 'Adicionar canal' }}
                    </button>
                    <button type="button" wire:click="cancelEdit"
                        class="px-5 py-2.5 bg-gray-700 hover:bg-gray-600 text-white text-sm rounded-lg transition-colors">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── MODO BYOP (REGISTRO DE NÚMERO PRÓPRIO) ── --}}
    @if ($mode === 'byop')
        <div>
            <button wire:click="backToSelection" class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-300 transition-colors mb-5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Voltar
            </button>

            <div class="flex items-center gap-2 mb-3">
                <span class="text-lg">📱</span>
                <h3 class="font-semibold text-white">Registrar meu número WhatsApp</h3>
            </div>

            <div class="bg-amber-950/40 border border-amber-800/60 rounded-lg px-4 py-3 text-xs text-amber-300 space-y-2 mb-5">
                <p class="font-semibold text-amber-200">Antes de começar — verifique os pré-requisitos:</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
                    <div class="bg-gray-900/50 rounded-lg p-3 space-y-1">
                        <p class="font-medium text-amber-200">1. Meta Business Manager</p>
                        <p class="text-amber-400">Sua empresa precisa ter uma conta verificada no Meta Business Manager.</p>
                        <a href="https://business.facebook.com" target="_blank" class="text-blue-400 hover:underline inline-flex items-center gap-1">
                            Abrir Meta Business
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <div class="bg-gray-900/50 rounded-lg p-3 space-y-1">
                        <p class="font-medium text-amber-200">2. WABA ID</p>
                        <p class="text-amber-400">No Meta Business Manager, crie um <strong>WhatsApp Business Account (WABA)</strong> e copie o ID — você vai precisar dele no formulário.</p>
                    </div>
                    <div class="bg-gray-900/50 rounded-lg p-3 space-y-1">
                        <p class="font-medium text-amber-200">3. Twilio conectado ao Meta</p>
                        <p class="text-amber-400">No console Twilio, vá em <em>Messaging → Senders → WhatsApp</em> e conecte sua conta ao Meta Business Manager.</p>
                        <a href="https://console.twilio.com/us1/develop/sms/senders/whatsapp-senders" target="_blank" class="text-blue-400 hover:underline inline-flex items-center gap-1">
                            Console Twilio
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                </div>
            </div>

            @livewire('whats-app-sender-wizard', ['empresaId' => $empresaId], key('sender-wizard-'.$empresaId))
        </div>
    @endif

    {{-- ── LISTA DE CANAIS ── --}}
    @if($channels->isNotEmpty())
        <div class="border-t border-gray-800 pt-5 space-y-3">
            <div class="flex items-center justify-between">
                <h4 class="text-sm font-medium text-gray-300">Canais conectados</h4>
                @if ($mode !== null)
                    {{-- não mostrar botão se já está em algum modo --}}
                @else
                    <button wire:click="selectMode('manual')"
                        class="text-xs text-green-500 hover:text-green-400 transition-colors">
                        + Adicionar canal
                    </button>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-300">
                    <thead>
                        <tr class="text-gray-500 border-b border-gray-800 text-xs uppercase">
                            <th class="pb-2 pr-4">Nome</th>
                            <th class="pb-2 pr-4">Número</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                        @foreach($channels as $channel)
                            <tr class="hover:bg-gray-800/30">
                                <td class="py-2.5 pr-4">
                                    <span class="font-medium text-white">{{ $channel->nome }}</span>
                                    @if($channel->is_default)
                                        <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-900/50 text-green-400 border border-green-800">padrão</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4 font-mono text-xs text-gray-400">
                                    {{ $channel->numero }}
                                    <span class="block font-sans text-gray-400">{{ __('messages.daily_limit_short', ['limit' => $channel->limite_diario_prospeccao]) }}</span>
                                    @if ($channel->qualidade)
                                        @php
                                            $qualityClass = match ($channel->qualidade) {
                                                'HIGH'   => 'bg-green-900/40 text-green-300 border-green-800',
                                                'MEDIUM' => 'bg-yellow-900/40 text-yellow-300 border-yellow-800',
                                                'LOW'    => 'bg-red-900/40 text-red-300 border-red-800',
                                                default  => 'bg-gray-800 text-gray-300 border-gray-700',
                                            };
                                        @endphp
                                        <span class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded border font-sans {{ $qualityClass }}"
                                              title="{{ __('messages.channel_quality_checked', ['when' => $channel->saude_verificada_em?->diffForHumans() ?? '—']) }}">
                                            {{ __('messages.channel_quality_' . strtolower($channel->qualidade)) }}
                                        </span>
                                        @if ($channel->limite_mensagens)
                                            <span class="block font-sans text-gray-400">{{ __('messages.channel_messaging_limit', ['limit' => $channel->limite_mensagens]) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="py-2.5 pr-4">
                                    @if ($channel->isProspectingPaused())
                                        <div class="mb-1.5 text-xs text-amber-300">
                                            {{ __('messages.channel_prospecting_paused', ['motivo' => __('messages.channel_pause_reason_' . $channel->pausa_motivo)]) }}
                                            <button wire:click="retomarProspeccao({{ $channel->id }})"
                                                wire:confirm="{{ __('messages.channel_resume_confirm') }}"
                                                class="block mt-0.5 underline hover:text-amber-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-300 rounded">
                                                {{ __('messages.channel_resume') }}
                                            </button>
                                        </div>
                                    @endif
                                    <button wire:click="toggleAtivo({{ $channel->id }})"
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium transition-colors
                                            {{ $channel->ativo ? 'bg-green-900/40 text-green-400 hover:bg-green-900/60' : 'bg-gray-700 text-gray-400 hover:bg-gray-600' }}">
                                        {{ $channel->ativo ? 'ativo' : 'inativo' }}
                                    </button>
                                </td>
                                <td class="py-2.5">
                                    <div class="flex items-center gap-3 justify-end">
                                        @if(!$channel->is_default)
                                            <button wire:click="setDefault({{ $channel->id }})"
                                                class="text-xs text-gray-500 hover:text-green-400 transition-colors">
                                                Tornar padrão
                                            </button>
                                        @endif
                                        <button wire:click="edit({{ $channel->id }})"
                                            class="text-xs text-gray-500 hover:text-blue-400 transition-colors">
                                            Editar
                                        </button>
                                        <button wire:click="delete({{ $channel->id }})"
                                            wire:confirm="Remover este canal? As mensagens existentes não serão afetadas."
                                            class="text-xs text-gray-500 hover:text-red-400 transition-colors">
                                            Remover
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif ($mode === null)
        <p class="text-xs text-gray-600 text-center py-2">Nenhum canal conectado ainda.</p>
    @endif

</div>
