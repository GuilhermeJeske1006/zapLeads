<x-onboarding-layout :step="1">
    <div class="grid grid-cols-1 lg:grid-cols-2 shadow-2xl rounded-2xl overflow-hidden">

        {{-- Left: form --}}
        <div class="bg-gray-900 border border-gray-800 lg:border-r-0 lg:rounded-r-none rounded-2xl p-10">
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-2">{{ __('messages.register_title') }}</h2>
                <p class="text-gray-400 text-sm">{{ __('messages.register_subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="name" :value="__('Name')" />
                    <x-text-input id="name" class="block mt-1.5 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Email')" />
                    <x-text-input id="email" class="block mt-1.5 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="password" :value="__('Password')" />
                        <x-text-input id="password" class="block mt-1.5 w-full" type="password" name="password" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>

                    <div>
                        <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                        <x-text-input id="password_confirmation" class="block mt-1.5 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5" />
                    </div>
                </div>

                <x-primary-button class="w-full justify-center py-3 text-sm">
                    Criar conta →
                </x-primary-button>

                <p class="text-center text-sm text-gray-400">
                    {{ __('messages.already_registered') }}
                    <a href="{{ route('login') }}" class="text-green-400 hover:text-green-300 font-medium transition-colors">
                        {{ __('Log in') }}
                    </a>
                </p>
            </form>
        </div>

        {{-- Right: benefits panel --}}
        <div class="hidden lg:flex flex-col justify-center bg-gray-800/40 border border-gray-800 rounded-r-2xl p-10">
            <div class="mb-8">
                <span class="inline-block bg-green-500/15 text-green-400 text-xs font-semibold px-3 py-1.5 rounded-full uppercase tracking-wider mb-4">14 dias grátis</span>
                <h3 class="text-xl font-bold text-white mb-2">Tudo que você precisa para vender mais</h3>
                <p class="text-gray-400 text-sm">Catálogo digital + WhatsApp + IA — tudo integrado.</p>
            </div>

            <ul class="space-y-5">
                <li class="flex items-start gap-3">
                    <span class="text-xl mt-0.5">📦</span>
                    <div>
                        <p class="text-sm font-semibold text-white">Catálogo digital</p>
                        <p class="text-xs text-gray-400 mt-0.5">Link único para seus produtos com fotos e preços</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-xl mt-0.5">💬</span>
                    <div>
                        <p class="text-sm font-semibold text-white">WhatsApp integrado</p>
                        <p class="text-xs text-gray-400 mt-0.5">Receba pedidos direto no WhatsApp, com histórico completo</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-xl mt-0.5">🤖</span>
                    <div>
                        <p class="text-sm font-semibold text-white">IA para leads</p>
                        <p class="text-xs text-gray-400 mt-0.5">Score automático e respostas inteligentes para cada cliente</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="text-xl mt-0.5">📍</span>
                    <div>
                        <p class="text-sm font-semibold text-white">Prospecção geográfica</p>
                        <p class="text-xs text-gray-400 mt-0.5">Encontre clientes na sua região diretamente no mapa</p>
                    </div>
                </li>
            </ul>

            <div class="mt-10 pt-6 border-t border-gray-700/60">
                <p class="text-xs text-gray-500">Sem compromisso. Cancele quando quiser.</p>
            </div>
        </div>

    </div>
</x-onboarding-layout>
