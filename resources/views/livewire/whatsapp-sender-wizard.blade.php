<div class="space-y-4">

    {{-- Step indicator --}}
    <div class="flex items-center gap-2 text-xs text-gray-500">
        @foreach (['Número', 'Verificação', 'Status'] as $i => $label)
            @php $n = $i + 1; @endphp
            <div class="flex items-center gap-1">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold
                    {{ $step >= $n ? 'bg-green-600 text-white' : 'bg-gray-700 text-gray-400' }}">
                    {{ $n }}
                </span>
                <span class="{{ $step === $n ? 'text-white' : '' }}">{{ $label }}</span>
            </div>
            @if ($n < 3)
                <div class="flex-1 h-px {{ $step > $n ? 'bg-green-600' : 'bg-gray-700' }}"></div>
            @endif
        @endforeach
    </div>

    {{-- Alerts --}}
    @if ($errorMessage)
        <div class="bg-red-900/50 border border-red-700 text-red-300 rounded-lg px-4 py-2 text-sm">
            {{ $errorMessage }}
        </div>
    @endif
    @if ($successMessage)
        <div class="bg-green-900/50 border border-green-700 text-green-300 rounded-lg px-4 py-2 text-sm">
            {{ $successMessage }}
        </div>
    @endif

    {{-- Step 1: Dados do número --}}
    @if ($step === 1)
        <form wire:submit="submitStep1" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Número WhatsApp</label>
                    <input wire:model="numero" type="text" placeholder="whatsapp:+5511999990000"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    @error('numero') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">
                        WABA ID
                        <span class="text-gray-600 text-xs">(Meta Business Manager)</span>
                    </label>
                    <input wire:model="wabaId" type="text" placeholder="123456789012345"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    @error('wabaId') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Nome de exibição</label>
                    <input wire:model="profileName" type="text" placeholder="Minha Empresa Ltda"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    @error('profileName') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm text-gray-400 mb-1">Setor</label>
                    <select wire:model="profileVertical"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="Other">Outro</option>
                        <option value="Automotive">Automotivo</option>
                        <option value="Beauty, Spa and Salon">Beleza / Salão</option>
                        <option value="Clothing and Apparel">Vestuário</option>
                        <option value="Education">Educação</option>
                        <option value="Entertainment">Entretenimento</option>
                        <option value="Finance and Banking">Finanças / Banco</option>
                        <option value="Food and Grocery">Alimentação</option>
                        <option value="Hotel and Lodging">Hotelaria</option>
                        <option value="Medical and Health">Saúde</option>
                        <option value="Non-profit">ONG / Sem fins lucrativos</option>
                        <option value="Professional Services">Serviços profissionais</option>
                        <option value="Restaurant">Restaurante</option>
                        <option value="Shopping and Retail">Varejo</option>
                        <option value="Travel and Transportation">Transporte / Viagem</option>
                    </select>
                    @error('profileVertical') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Descrição breve <span class="text-gray-600 text-xs">(opcional)</span></label>
                <input wire:model="profileAbout" type="text" placeholder="Sua empresa em uma frase"
                    class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                @error('profileAbout') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Receber código via</label>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input wire:model="verificationMethod" type="radio" value="SMS"
                            class="text-green-500 border-gray-600 bg-gray-800 focus:ring-green-500">
                        SMS
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-300 cursor-pointer">
                        <input wire:model="verificationMethod" type="radio" value="VOICE"
                            class="text-green-500 border-gray-600 bg-gray-800 focus:ring-green-500">
                        Ligação de voz
                    </label>
                </div>
            </div>

            <div class="bg-yellow-900/30 border border-yellow-800 text-yellow-300 rounded-lg px-4 py-3 text-xs space-y-1">
                <p class="font-semibold">Pré-requisitos:</p>
                <ul class="list-disc list-inside space-y-0.5 text-yellow-400">
                    <li>Conta <strong>Meta Business Manager</strong> verificada</li>
                    <li>WhatsApp Business Account (WABA) criada no Meta</li>
                    <li>Conta Twilio conectada ao Meta Business Manager</li>
                </ul>
            </div>

            <div class="flex gap-2">
                <button type="submit"
                    class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="submitStep1">Enviar código</span>
                    <span wire:loading wire:target="submitStep1">Enviando...</span>
                </button>
                <button type="button" wire:click="resetWizard"
                    class="bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm px-4 py-2 rounded-lg transition">
                    Cancelar
                </button>
            </div>
        </form>
    @endif

    {{-- Step 2: OTP --}}
    @if ($step === 2)
        <div class="space-y-4">
            <p class="text-sm text-gray-400">
                Código enviado para <span class="text-white font-mono">{{ $numero }}</span> via {{ $verificationMethod }}.
                Insira o código abaixo.
            </p>

            <form wire:submit="submitStep2" class="space-y-3">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Código de verificação</label>
                    <input wire:model="otpCode" type="text" placeholder="123456" maxlength="8"
                        class="w-full bg-gray-800 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm font-mono tracking-widest focus:outline-none focus:ring-2 focus:ring-green-500">
                    @error('otpCode') <span class="text-red-400 text-xs">{{ $message }}</span> @enderror
                </div>

                <div class="flex gap-2">
                    <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition"
                        wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="submitStep2">Confirmar</span>
                        <span wire:loading wire:target="submitStep2">Verificando...</span>
                    </button>
                    <button type="button" wire:click="resendOtp"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm px-4 py-2 rounded-lg transition"
                        wire:loading.attr="disabled" wire:target="resendOtp">
                        Reenviar código
                    </button>
                    <button type="button" wire:click="resetWizard"
                        class="text-gray-500 hover:text-gray-400 text-sm px-3 py-2 transition">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Step 3: Status --}}
    @if ($step === 3)
        <div class="space-y-4">
            @php $status = $registration?->status; @endphp

            @if ($status === 'approved')
                <div class="bg-green-900/50 border border-green-700 text-green-300 rounded-lg px-4 py-3 text-sm space-y-1">
                    <p class="font-semibold">Número aprovado pelo Meta!</p>
                    <p class="text-green-400 text-xs">Canal WhatsApp criado automaticamente e pronto para uso.</p>
                </div>

            @elseif ($status === 'rejected')
                <div class="bg-red-900/50 border border-red-700 text-red-300 rounded-lg px-4 py-3 text-sm space-y-1">
                    <p class="font-semibold">Registro rejeitado pelo Meta.</p>
                    <p class="text-red-400 text-xs">Verifique se o nome de exibição segue as diretrizes do Meta e tente novamente.</p>
                </div>

            @elseif ($status === 'failed')
                <div class="bg-red-900/50 border border-red-700 text-red-300 rounded-lg px-4 py-3 text-sm">
                    <p class="font-semibold">Erro no processo.</p>
                    @if ($registration?->error_message)
                        <p class="text-red-400 text-xs mt-1 font-mono">{{ $registration->error_message }}</p>
                    @endif
                </div>

            @else
                <div class="bg-yellow-900/30 border border-yellow-800 text-yellow-300 rounded-lg px-4 py-3 text-sm space-y-1">
                    <p class="font-semibold">Aguardando aprovação do Meta</p>
                    <p class="text-yellow-400 text-xs">O processo pode levar de algumas horas a 7 dias. Você receberá uma notificação quando aprovado.</p>
                </div>
                <div class="text-xs text-gray-500">
                    Número: <span class="text-gray-300 font-mono">{{ $numero }}</span>
                </div>
            @endif

            <div class="flex gap-2">
                @if (!in_array($status, ['approved']))
                    <button type="button" wire:click="refreshStatus"
                        class="bg-gray-700 hover:bg-gray-600 text-gray-300 text-sm px-4 py-2 rounded-lg transition"
                        wire:loading.attr="disabled" wire:target="refreshStatus">
                        <span wire:loading.remove wire:target="refreshStatus">Verificar status</span>
                        <span wire:loading wire:target="refreshStatus">Verificando...</span>
                    </button>
                @endif
                <button type="button" wire:click="resetWizard"
                    class="text-gray-500 hover:text-gray-400 text-sm px-3 py-2 transition">
                    {{ $status === 'approved' ? 'Fechar' : 'Cancelar' }}
                </button>
            </div>
        </div>
    @endif
</div>
