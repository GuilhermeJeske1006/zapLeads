<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - {{ $title ?? '' }}</title>

    <link rel="icon" type="image/png" href="{{ asset('favicon/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-gray-950 text-gray-100 font-inter antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

<div class="flex h-screen overflow-hidden">
    {{-- Sidebar: fixed column from md up, a drawer below it --}}
    <div x-show="sidebarOpen" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/60 md:hidden" @click="sidebarOpen = false" aria-hidden="true"></div>

    <aside id="app-sidebar"
           class="hidden md:flex flex-col w-64 bg-gray-900 border-r border-gray-800 flex-shrink-0"
           :class="sidebarOpen && 'max-md:flex max-md:fixed max-md:inset-y-0 max-md:left-0 max-md:z-50 max-md:shadow-2xl'">
        {{-- Logo --}}
        <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-800">
            <img src="{{ asset('brand/logo.png') }}" alt="" class="h-8">
            <span class="text-lg font-bold text-white">{{ config('app.name') }}</span>
            <button type="button" @click="sidebarOpen = false" aria-label="{{ __('messages.close_menu') }}"
                    class="md:hidden ml-auto w-8 h-8 flex items-center justify-center text-gray-400 hover:text-white rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto" aria-label="{{ __('messages.main_menu') }}">
            @include('layouts.partials.nav')
        </nav>

        {{-- User --}}
        <div class="px-3 py-3 border-t border-gray-800" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button @click="open = !open" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg transition-colors group {{ request()->routeIs('profile*') ? 'bg-green-600/10 ring-1 ring-green-600/20' : 'hover:bg-gray-800' }}" type="button">
                <div class="w-8 h-8 bg-green-600/20 border border-green-600/30 rounded-full flex items-center justify-center text-sm font-semibold text-green-400 shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0 text-left">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ auth()->user()->email }}</p>
                </div>
                <svg class="w-4 h-4 text-gray-500 shrink-0 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Dropdown --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-100"
                x-transition:enter-start="opacity-0 translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                x-transition:leave="transition ease-in duration-75"
                x-transition:leave-start="opacity-100 translate-y-0"
                x-transition:leave-end="opacity-0 translate-y-1"
                @click.outside="open = false"
                class="mt-1 bg-gray-800 border border-gray-700 rounded-lg overflow-hidden shadow-lg"
                style="display: none"
            >
                <a href="{{ route('profile.edit') }}"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ __('Profile') }}
                </a>
            
                <a href="{{ route('billing.index') }}"
                   class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-gray-700 hover:text-white transition-colors">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3v1H8a2 2 0 00-2 2v4a2 2 0 002 2h8a2 2 0 002-2v-4a2 2 0 00-2-2h-1v-1c0-1.657-1.343-3-3-3z m-1 4v-1a1 1 0 112 0v1h-2z"/>
                    </svg>
                    {{ __('messages.billing') }}
                </a>

                <div class="border-t border-gray-700">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-red-600/10 hover:text-red-400 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            {{ __('messages.logout') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- Main --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">
        {{-- Topbar --}}
        <header class="bg-gray-900 border-b border-gray-800 px-4 sm:px-6 py-4 flex items-center gap-3 sm:gap-4">
            <button type="button" @click="sidebarOpen = true" aria-controls="app-sidebar" :aria-expanded="sidebarOpen.toString()" aria-label="{{ __('messages.open_menu') }}"
                    class="md:hidden w-9 h-9 -ml-1 flex items-center justify-center text-gray-300 hover:text-white hover:bg-gray-800 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-lg font-semibold text-white truncate">{{ $title ?? '' }}</h1>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('lang.switch', 'pt_BR') }}" lang="pt-BR" title="Português" @if (app()->getLocale() === 'pt_BR') aria-current="true" @endif
                   class="px-2 py-1 text-xs rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400 {{ app()->getLocale() === 'pt_BR' ? 'bg-green-600 text-white' : 'text-gray-300 hover:text-white' }}">PT</a>
                <a href="{{ route('lang.switch', 'es') }}" lang="es" title="Español" @if (app()->getLocale() === 'es') aria-current="true" @endif
                   class="px-2 py-1 text-xs rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400 {{ app()->getLocale() === 'es' ? 'bg-green-600 text-white' : 'text-gray-300 hover:text-white' }}">ES</a>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-4 sm:p-6">
            @if (session('success'))
                <div class="mb-4 px-4 py-3 bg-green-600/20 border border-green-600/30 rounded-xl text-green-400 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>

{{-- Toast (Livewire events) --}}
<div
    x-data="{ show: false, message: '', type: 'success' }"
    x-on:toast.window="message = $event.detail[0]?.message ?? $event.detail.message; type = $event.detail[0]?.type ?? $event.detail.type ?? 'success'; show = true; setTimeout(() => show = false, 3000)"
    x-show="show"
    x-transition
    class="fixed bottom-6 right-6 z-50 px-4 py-3 rounded-xl text-sm font-medium shadow-lg"
    :class="{ success: 'bg-green-600 text-white', info: 'bg-gray-700 text-white' }[type] ?? 'bg-red-600 text-white'"
    style="display:none"
>
    <span x-text="message"></span>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
