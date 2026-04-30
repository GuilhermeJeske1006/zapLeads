<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'ZapLeads') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('favicon/favicon-96x96.png') }}" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon/favicon.svg') }}" />
    <link rel="shortcut icon" href="{{ asset('favicon/favicon.ico') }}" />
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon/apple-touch-icon.png') }}" />
    <link rel="manifest" href="{{ asset('favicon/site.webmanifest') }}" />
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-950 text-gray-100 font-inter antialiased">
<div class="min-h-screen flex">

    {{-- Left Panel --}}
    <div class="hidden lg:flex lg:w-1/2 bg-gray-900 border-r border-gray-800 flex-col items-center justify-center p-12 relative overflow-hidden">
        <div class="absolute inset-0 bg-linear-to-br from-green-600/10 via-transparent to-transparent pointer-events-none"></div>
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-green-500/5 rounded-full pointer-events-none"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-green-500/5 rounded-full pointer-events-none"></div>

        <div class="relative z-10 max-w-sm w-full">
            <div class="flex items-center gap-4 mb-10">
                <img src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name') }}" class="h-12  shrink-0">
                <div>
                    <h1 class="text-2xl font-bold text-white">{{ config('app.name') }}</h1>
                    <p class="text-gray-500 text-sm">{{ __('messages.whatsapp_automation_slogan') }}</p>
                </div>
            </div>

            <p class="text-gray-300 text-base mb-10 leading-relaxed">
                {{ __('messages.whatsapp_automation_full_desc') }}
            </p>

            <div class="space-y-5">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-green-600/20 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ __('messages.digital_catalog_feature') }}</p>
                        <p class="text-gray-500 text-xs mt-0.5">{{ __('messages.digital_catalog_subtitle') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-green-600/20 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ __('messages.whatsapp_automation_feature') }}</p>
                        <p class="text-gray-500 text-xs mt-0.5">{{ __('messages.whatsapp_automation_subtitle') }}</p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 bg-green-600/20 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-white text-sm font-medium">{{ __('messages.ai_lead_capture') }}</p>
                        <p class="text-gray-500 text-xs mt-0.5">{{ __('messages.ai_prospecting_subtitle') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Right Panel --}}
    <div class="flex-1 flex flex-col items-center justify-center p-6 lg:p-12">
        <div class="absolute top-4 right-6 flex items-center gap-2">
            <a href="{{ route('lang.switch', 'pt_BR') }}"
               class="px-2 py-1 text-xs rounded {{ app()->getLocale() === 'pt_BR' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white' }}">PT</a>
            <a href="{{ route('lang.switch', 'es') }}"
               class="px-2 py-1 text-xs rounded {{ app()->getLocale() === 'es' ? 'bg-green-600 text-white' : 'text-gray-400 hover:text-white' }}">ES</a>
        </div>

        {{-- Mobile logo --}}
        <div class="lg:hidden flex items-center gap-3 mb-8">
            <img src="{{ asset('brand/logo.png') }}" alt="{{ config('app.name') }}" class=" h-10 ">
            <span class="text-xl font-bold text-white">{{ config('app.name') }}</span>
        </div>

        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>
    </div>
</div>

@livewireScripts
</body>
</html>
