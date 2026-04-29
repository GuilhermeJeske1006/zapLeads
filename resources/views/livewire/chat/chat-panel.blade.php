<div class="flex h-full bg-gray-950 rounded-2xl border border-gray-800 overflow-hidden" style="height: calc(100vh - 8rem)">

    {{-- Conversation List --}}
    <div class="w-80 flex-shrink-0 flex flex-col border-r border-gray-800 bg-gray-900">
        {{-- Search --}}
        <div class="p-4 border-b border-gray-800">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                       type="text"
                       placeholder="{{ __('messages.search_conversations') }}"
                       class="w-full pl-9 pr-4 py-2 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500 transition-colors"/>
            </div>
        </div>

        {{-- List --}}
        <div class="flex-1 overflow-y-auto divide-y divide-gray-800/50">
            @forelse ($conversations as $conv)
                <button wire:click="selectConversation({{ $conv->id }})"
                        class="w-full px-4 py-3 flex items-start gap-3 hover:bg-gray-800/50 transition-colors text-left
                               {{ $activeConversationId === $conv->id ? 'bg-gray-800' : '' }}">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                        {{ strtoupper(substr($conv->display_name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <span class="text-sm font-semibold text-white truncate">{{ $conv->display_name }}</span>
                            <span class="text-xs text-gray-500 ml-2 flex-shrink-0">
                                {{ $conv->last_message_at?->format('H:i') }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-400 truncate mt-0.5">{{ $conv->last_message }}</p>
                    </div>
                    @if ($conv->unread_count > 0)
                        <span class="flex-shrink-0 w-5 h-5 bg-green-500 rounded-full text-xs text-white flex items-center justify-center font-bold">
                            {{ $conv->unread_count }}
                        </span>
                    @endif
                </button>
            @empty
                <div class="p-8 text-center text-gray-500 text-sm">
                    {{ __('messages.no_conversations') }}
                </div>
            @endforelse
        </div>
    </div>

    {{-- Chat Area --}}
    <div class="flex-1 flex flex-col">
        @if ($activeConversation)
            {{-- Chat Header --}}
            <div class="px-6 py-4 bg-gray-900 border-b border-gray-800 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-500 to-emerald-700 flex items-center justify-center text-sm font-bold">
                    {{ strtoupper(substr($activeConversation->display_name, 0, 1)) }}
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-white">{{ $activeConversation->display_name }}</h3>
                    <p class="text-xs text-gray-400">{{ $activeConversation->telefone }}</p>
                </div>
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto p-6 space-y-3"
                 id="messages-container"
                 x-data
                 x-init="$el.scrollTop = $el.scrollHeight"
                 x-on:message-sent.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)"
                 x-on:new-message-notification.window="$nextTick(() => $el.scrollTop = $el.scrollHeight)">
                @foreach ($messages as $msg)
                    <div class="flex {{ $msg->isFromUser() ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-sm lg:max-w-md xl:max-w-lg">
                            <div class="px-4 py-2.5 rounded-2xl text-sm
                                {{ $msg->isFromUser()
                                    ? 'bg-green-600 text-white rounded-br-sm'
                                    : 'bg-gray-800 text-gray-100 rounded-bl-sm' }}">
                                @if ($msg->type === 'image' && $msg->media_url)
                                    <img src="{{ $msg->media_url }}" alt="" class="rounded-lg max-w-xs mb-1">
                                @endif
                                {{ $msg->message }}
                                @if ($msg->ai_generated)
                                    <span class="ml-1 text-xs opacity-60">✨ AI</span>
                                @endif
                            </div>
                            <div class="flex {{ $msg->isFromUser() ? 'justify-end' : 'justify-start' }} mt-1 items-center gap-1">
                                <span class="text-xs text-gray-500">{{ $msg->created_at->format('H:i') }}</span>
                                @if ($msg->isFromUser())
                                    <span class="text-xs text-gray-500">
                                        @if ($msg->status === 'sent') ✓
                                        @elseif ($msg->status === 'delivered') ✓✓
                                        @elseif ($msg->status === 'read') <span class="text-blue-400">✓✓</span>
                                        @elseif ($msg->status === 'failed') <span class="text-red-400">✗</span>
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- AI Suggestion --}}
            @if ($aiSuggestion)
                <div class="px-6 py-3 bg-gray-900/50 border-t border-gray-800">
                    <div class="flex items-start gap-3 bg-gray-800 rounded-xl p-3">
                        <span class="text-lg flex-shrink-0">✨</span>
                        <div class="flex-1">
                            <p class="text-xs text-green-400 font-medium mb-1">{{ __('messages.ai_suggestion') }}</p>
                            <p class="text-sm text-gray-200">{{ $aiSuggestion }}</p>
                        </div>
                        <button wire:click="useAISuggestion"
                                class="flex-shrink-0 px-3 py-1 bg-green-600 text-white text-xs rounded-lg hover:bg-green-500 transition-colors">
                            {{ __('messages.use') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Input --}}
            <div class="px-6 py-4 bg-gray-900 border-t border-gray-800">
                <div class="flex items-end gap-3">
                    {{-- AI Button --}}
                    <button wire:click="suggestWithAI"
                            wire:loading.attr="disabled"
                            class="flex-shrink-0 w-10 h-10 bg-gray-800 hover:bg-gray-700 rounded-xl flex items-center justify-center transition-colors"
                            title="{{ __('messages.ai_suggest_reply') }}">
                        <span wire:loading.remove wire:target="suggestWithAI" class="text-lg">✨</span>
                        <svg wire:loading wire:target="suggestWithAI" class="w-4 h-4 text-green-400 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>

                    <div class="flex-1 relative">
                        <textarea wire:model="newMessage"
                                  wire:keydown.enter.prevent="sendMessage"
                                  rows="1"
                                  placeholder="{{ __('messages.type_message') }}"
                                  class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-xl text-sm text-gray-200 placeholder-gray-500 focus:outline-none focus:border-green-500 transition-colors resize-none"></textarea>
                    </div>

                    <button wire:click="sendMessage"
                            wire:loading.attr="disabled"
                            class="flex-shrink-0 w-10 h-10 bg-green-600 hover:bg-green-500 rounded-xl flex items-center justify-center transition-colors">
                        <svg wire:loading.remove wire:target="sendMessage" class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                        <svg wire:loading wire:target="sendMessage" class="w-4 h-4 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @else
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto mb-4 bg-gray-800 rounded-full flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                    </div>
                    <p class="text-gray-500 text-sm">{{ __('messages.select_conversation') }}</p>
                </div>
            </div>
        @endif
    </div>
</div>
