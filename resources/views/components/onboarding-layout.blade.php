<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ZapLeads') }} — {{ __('messages.setup') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="bg-gray-950 text-gray-100 font-inter antialiased">

<div class="min-h-screen flex flex-col">

    {{-- Header --}}
    <header class="border-b border-gray-800 bg-gray-900/50 backdrop-blur-sm">
        <div class="max-w-2xl mx-auto px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name') }}" class=" h-8">
                <span class="font-semibold text-white">{{ config('app.name') }}</span>
            </div>

            <div class="flex items-center gap-4 text-sm text-gray-400">
                @auth
                    <span>{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-gray-500 hover:text-gray-300 transition-colors text-xs">{{ __('messages.logout') }}</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-gray-400 hover:text-white transition-colors text-xs">
                        {{ __('messages.already_have_account') }}
                    </a>
                @endauth
            </div>
        </div>
    </header>

    {{-- Progress Bar --}}
    <div class="border-b border-gray-800 bg-gray-900/30">
        <div class="max-w-2xl mx-auto px-6 py-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs text-gray-400 font-medium uppercase tracking-wider">{{ __('messages.account_setup') }}</span>
                <span class="text-xs text-gray-500">Passo {{ $step }} de 4</span>
            </div>
            <div class="flex gap-2">
                <div class="h-1.5 flex-1 rounded-full {{ $step >= 1 ? 'bg-green-500' : 'bg-gray-800' }}"></div>
                <div class="h-1.5 flex-1 rounded-full {{ $step >= 2 ? 'bg-green-500' : 'bg-gray-800' }}"></div>
                <div class="h-1.5 flex-1 rounded-full {{ $step >= 3 ? 'bg-green-500' : 'bg-gray-800' }}"></div>
                <div class="h-1.5 flex-1 rounded-full {{ $step >= 4 ? 'bg-green-500' : 'bg-gray-800' }}"></div>
            </div>
            <div class="flex justify-between mt-2">
                <span class="text-xs {{ $step >= 1 ? 'text-green-400' : 'text-gray-600' }}">Conta</span>
                <span class="text-xs {{ $step >= 2 ? 'text-green-400' : 'text-gray-600' }}">Empresa</span>
                <span class="text-xs {{ $step >= 3 ? 'text-green-400' : 'text-gray-600' }}">Plano</span>
                <span class="text-xs {{ $step >= 4 ? 'text-green-400' : 'text-gray-600' }}">Pagamento</span>
            </div>
        </div>
    </div>

    {{-- Content --}}
    <div class="flex-1 flex flex-col items-center justify-center px-6 py-12">
        <div class="w-full max-w-xl">
            {{ $slot }}
        </div>
    </div>
</div>

@livewireScripts
@stack('scripts')
</body>
</html>
