<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} - {{ $title ?? '' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('styles')
</head>
<body class="bg-gray-950 text-gray-100 font-inter antialiased" x-data="{ sidebarOpen: true }">

<div class="flex h-screen overflow-hidden">
    {{-- Sidebar --}}
    <aside class="flex flex-col w-64 bg-gray-900 border-r border-gray-800 transition-all duration-300 flex-shrink-0">
        {{-- Logo --}}
        <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-800">
            <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
            </div>
            <span class="text-lg font-bold text-white">Catálogo AR</span>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
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
        <header class="bg-gray-900 border-b border-gray-800 px-6 py-4 flex items-center gap-4">
            <h1 class="text-lg font-semibold text-white">{{ $title ?? '' }}</h1>
            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('lang.switch', 'pt_BR') }}"
                   class="px-2 py-1 text-xs rounded {{ app()->getLocale() === 'pt_BR' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white' }}">PT</a>
                <a href="{{ route('lang.switch', 'es') }}"
                   class="px-2 py-1 text-xs rounded {{ app()->getLocale() === 'es' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white' }}">ES</a>
            </div>
        </header>

        {{-- Content --}}
        <main class="flex-1 overflow-y-auto p-6">
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
    :class="type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
    style="display:none"
>
    <span x-text="message"></span>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@livewireScripts
@stack('scripts')
</body>
</html>
