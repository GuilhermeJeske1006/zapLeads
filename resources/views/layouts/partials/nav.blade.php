@php
$navItems = [
    ['route' => 'dashboard', 'label' => __('messages.nav_dashboard'), 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
    ['route' => 'prospeccao.index', 'label' => __('messages.nav_prospecting'), 'icon' => 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 13l-6 3'],
    ['route' => 'leads.index', 'label' => __('messages.nav_leads'), 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
    ['route' => 'chat.index', 'label' => __('messages.nav_chat'), 'icon' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
    ['route' => 'produtos.index', 'label' => __('messages.nav_products'), 'icon' => 'M20 13V7a2 2 0 00-1-1l-7-4a2 2 0 00-2 0L3 6a2 2 0 00-1 1v6a2 2 0 001 1l7 4a2 2 0 002 0l7-4a2 2 0 001-1zM3 7l9 5 9-5M12 22V12'],
    ['route' => 'empresa.edit', 'label' => __('messages.nav_empresa'), 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
];

if (auth()->user()?->isMasterAdmin()) {
    $navItems[] = ['route' => 'admin.subscriptions.index', 'label' => 'Admin', 'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'];
    $navItems[] = ['route' => 'admin.costs.index', 'label' => __('messages.nav_admin_costs'), 'icon' => 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z'];
}
@endphp

@foreach ($navItems as $item)
    @if (\Illuminate\Support\Facades\Route::has($item['route']))
        <a href="{{ route($item['route']) }}"
           @if (request()->routeIs($item['route'] . '*')) aria-current="page" @endif
           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors group focus:outline-none focus-visible:ring-2 focus-visible:ring-green-400
                  {{ request()->routeIs($item['route'] . '*') ? 'bg-green-600/20 text-green-300' : 'text-gray-300 hover:text-white hover:bg-gray-800' }}">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $item['icon'] }}"/>
            </svg>
            {{ $item['label'] }}
        </a>
    @endif
@endforeach
