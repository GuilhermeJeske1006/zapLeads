<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $loja->nome }} - {{ __('messages.catalog') }}</title>
    <meta name="description" content="{{ $loja->nome }} - {{ __('messages.digital_catalog') }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .product-card:hover .product-img { transform: scale(1.05); }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen">

    {{-- Header --}}
    <header class="bg-gray-900 border-b border-gray-800 sticky top-0 z-40">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-green-500 to-emerald-700 flex items-center justify-center overflow-hidden flex-shrink-0">
                @if ($loja->logo)
                    <img src="{{ $loja->logo_url }}" alt="{{ $loja->nome }}" class="w-full h-full object-cover">
                @else
                    <span class="text-lg font-bold text-white">{{ strtoupper(substr($loja->nome, 0, 1)) }}</span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <h1 class="text-base font-bold text-white truncate">{{ $loja->nome }}</h1>
                <p class="text-xs text-gray-400 truncate">{{ $loja->cidade }}</p>
            </div>
            <a href="#contact-form"
               class="flex-shrink-0 px-4 py-2 bg-green-600 hover:bg-green-500 text-white text-sm font-medium rounded-xl transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                </svg>
                WhatsApp
            </a>
        </div>
    </header>

    <main class="max-w-4xl mx-auto px-4 py-8">

        {{-- Products --}}
        <section class="mb-12">
            <h2 class="text-xl font-bold text-white mb-6">{{ __('messages.our_products') }}</h2>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @forelse ($produtos as $produto)
                    <div class="product-card bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden">
                        <div class="aspect-square overflow-hidden bg-gray-800">
                            <img src="{{ $produto->imagem_url }}"
                                 alt="{{ $produto->nome }}"
                                 class="product-img w-full h-full object-cover transition-transform duration-300"
                                 onerror="this.src='https://ui-avatars.com/api/?name={{ urlencode($produto->nome) }}&background=1f2937&color=9ca3af&size=256'">
                        </div>
                        <div class="p-3">
                            <p class="text-sm font-semibold text-white leading-tight mb-1">{{ $produto->nome }}</p>
                            @if ($produto->descricao)
                                <p class="text-xs text-gray-400 mb-2 line-clamp-2">{{ $produto->descricao }}</p>
                            @endif
                            <p class="text-base font-bold text-green-400">{{ $produto->preco_formatado }}</p>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12 text-gray-500">
                        {{ __('messages.no_products_yet') }}
                    </div>
                @endforelse
            </div>
        </section>

        {{-- Lead Capture Form --}}
        <section id="contact-form" class="bg-gray-900 border border-gray-800 rounded-2xl p-6">
            <div class="text-center mb-6">
                <div class="w-16 h-16 mx-auto mb-4 bg-green-600/20 rounded-2xl flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">{{ __('messages.contact_whatsapp') }}</h2>
                <p class="text-sm text-gray-400">{{ __('messages.contact_description') }}</p>
            </div>

            <form id="lead-form" class="space-y-4 max-w-sm mx-auto">
                @csrf
                <div>
                    <input type="text" id="nome" name="nome" required
                           placeholder="{{ __('messages.your_name') }}"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500 transition-colors">
                </div>
                <div>
                    <input type="tel" id="telefone" name="telefone" required
                           placeholder="{{ __('messages.your_phone') }}"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500 transition-colors">
                </div>
                <button type="submit" id="submit-btn"
                        class="w-full py-3 bg-green-600 hover:bg-green-500 text-white font-semibold rounded-xl transition-colors flex items-center justify-center gap-3">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                    </svg>
                    {{ __('messages.open_whatsapp') }}
                </button>
                <div id="geo-status" class="text-xs text-center text-gray-500 hidden">
                    📍 {{ __('messages.getting_location') }}
                </div>
            </form>
        </section>
    </main>

<script>
const slug = '{{ $loja->slug }}';
let userLat = null, userLon = null;

// Get geolocation quietly in background
if (navigator.geolocation) {
    document.getElementById('geo-status').classList.remove('hidden');
    navigator.geolocation.getCurrentPosition(
        pos => {
            userLat = pos.coords.latitude;
            userLon = pos.coords.longitude;
            document.getElementById('geo-status').classList.add('hidden');
        },
        () => document.getElementById('geo-status').classList.add('hidden'),
        { timeout: 5000 }
    );
}

document.getElementById('lead-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg> {{ __("messages.sending") }}...';

    const payload = {
        nome: document.getElementById('nome').value,
        telefone: document.getElementById('telefone').value,
        _token: document.querySelector('[name=_token]').value,
    };
    if (userLat) { payload.latitude = userLat; payload.longitude = userLon; }

    try {
        const res = await fetch(`/loja/${slug}/lead`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': payload._token },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            window.open(data.whatsapp_url, '_blank');
        }
    } catch(err) {
        console.error(err);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '{{ __("messages.open_whatsapp") }}';
    }
});
</script>
</body>
</html>
