<?php

namespace App\Livewire\Chat;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Empresa;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppChannel;
use App\Repositories\ConversationRepository;
use App\Services\AIService;
use App\Services\WhatsAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

class ChatPanel extends Component
{
    public ?int $activeConversationId = null;
    public ?Conversation $activeConversation = null;
    public string $newMessage = '';
    public string $search = '';
    public string $aiSuggestion = '';
    public bool $loadingAI = false;
    public ?int $selectedChannelId = null;

    protected ConversationRepository $conversationRepo;
    protected AIService $aiService;
    protected WhatsAppService $whatsApp;
    protected Empresa $empresa;

    public function boot(ConversationRepository $conversationRepo, AIService $aiService, WhatsAppService $whatsApp): void
    {
        $this->conversationRepo = $conversationRepo;
        $this->aiService = $aiService;
        $this->whatsApp = $whatsApp;
        $this->empresa = auth()->user()->empresa()->firstOrCreate([]);
    }

    public function getListeners(): array
    {
        $empresaId = $this->empresa?->id;
        return [
            "echo-private:empresa.{$empresaId}.chat,.message.received" => 'onMessageReceived',
            "echo-private:conversation.{$this->activeConversationId},.message.sent" => 'onMessageStatusUpdated',
            "echo-private:conversation.{$this->activeConversationId},.message.status.updated" => 'onMessageStatusUpdated',
        ];
    }

    public function selectConversation(int $id): void
    {
        $this->activeConversationId = $id;
        $this->activeConversation = Conversation::with('messages')->find($id);
        $this->conversationRepo->markAsRead($this->activeConversation);
        $this->aiSuggestion = '';

        $this->selectedChannelId = $this->activeConversation->whatsapp_channel_id
            ?? $this->empresa->defaultChannel()?->id;

        $this->dispatch('conversation-selected', id: $id);
    }

    public function changeChannel(int $channelId): void
    {
        if (!$this->activeConversation) {
            return;
        }

        $channel = WhatsAppChannel::where('id', $channelId)
            ->where('empresa_id', $this->empresa->id)
            ->first();

        if (!$channel) {
            return;
        }

        $this->selectedChannelId = $channelId;
        $this->activeConversation->update(['whatsapp_channel_id' => $channelId]);
        $this->activeConversation->refresh();
    }

    public function sendMessage(): void
    {
        $this->validate(['newMessage' => 'required|string|max:4096']);

        if (!$this->activeConversation) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $this->activeConversationId,
            'sender' => 'user',
            'message' => $this->newMessage,
            'type' => 'text',
            'status' => 'sending',
        ]);

        $this->activeConversation->update([
            'last_message' => $this->newMessage,
            'last_message_at' => now(),
        ]);

        $this->newMessage = '';
        $this->aiSuggestion = '';

        $phone     = $this->activeConversation->telefone;
        $empresaId = $this->activeConversation->empresa_id;
        $channel   = $this->activeConversation->whatsappChannel;

        $result = match ($message->type) {
            'image' => $this->whatsApp->sendImageMessage($phone, $message->media_url, $message->message, $empresaId, $channel),
            default => $this->whatsApp->sendTextMessage($phone, $message->message, $empresaId, $channel),
        };

        $status = $result['success'] ? 'sent' : 'failed';
        $message->update([
            'status'             => $status,
            'twilio_message_sid' => $result['data']['sid'] ?? null,
            'error_code'         => $result['code'] ?? null,
        ]);

        if ($result['success']) {
            $e164 = $this->activeConversation->telefone_e164;
            $lead = $this->activeConversation->lead_id
                ? $this->activeConversation->lead
                : ($e164 ? Lead::where('empresa_id', $empresaId)->where('telefone_e164', $e164)->first() : null);

            if ($lead && ($lead->status ?? 'novo') === 'novo') {
                $lead->update(['status' => 'contatado']);
            }
        } else {
            Log::warning('WhatsApp message failed', ['message_id' => $message->id]);
        }

        broadcast(new MessageSent($message));

        $this->dispatch('message-sent');
        $this->activeConversation->refresh();
    }

    public function suggestWithAI(): void
    {
        if (!$this->activeConversation) {
            return;
        }

        $this->loadingAI = true;
        $this->aiSuggestion = $this->aiService->gerarMensagem($this->activeConversation);
        $this->loadingAI = false;
    }

    public function useAISuggestion(): void
    {
        $this->newMessage = $this->aiSuggestion;
        $this->aiSuggestion = '';
    }

    public function onMessageReceived(array $data): void
    {
        if ($this->activeConversationId === $data['conversation']['id']) {
            $this->activeConversation?->refresh();
            $this->conversationRepo->markAsRead($this->activeConversation);
        }
        $this->dispatch('new-message-notification',
            conversation: $data['conversation'],
            activeConversationId: $this->activeConversationId,
        );
    }

    public function onMessageStatusUpdated(array $data): void
    {
        $this->activeConversation?->refresh();
    }

    public function render()
    {
        $conversations = $this->conversationRepo->getForEmpresa($this->empresa, $this->search);

        $messages = $this->activeConversation
            ? $this->activeConversation->messages()->orderBy('created_at')->get()
            : collect();

        $channels = WhatsAppChannel::where('empresa_id', $this->empresa->id)
            ->where('ativo', true)
            ->orderByDesc('is_default')
            ->orderBy('nome')
            ->get();

        return view('livewire.chat.chat-panel', compact('conversations', 'messages', 'channels'));
    }
}
