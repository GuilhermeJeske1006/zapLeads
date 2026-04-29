<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Loja;
use Illuminate\Database\Eloquent\Collection;

class ConversationRepository
{
    public function getForLoja(Loja $loja, string $search = ''): Collection
    {
        return Conversation::query()
            ->where('loja_id', $loja->id)
            ->where('status', 'active')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nome_contato', 'like', "%{$search}%")
                  ->orWhere('telefone', 'like', "%{$search}%")
                  ->orWhere('last_message', 'like', "%{$search}%");
            }))
            ->orderByDesc('last_message_at')
            ->with('lead')
            ->get();
    }

    public function findOrCreateByPhone(Loja $loja, string $phone, string $nome = null): Conversation
    {
        return Conversation::firstOrCreate(
            ['loja_id' => $loja->id, 'telefone' => $phone],
            ['nome_contato' => $nome, 'status' => 'active']
        );
    }

    public function markAsRead(Conversation $conversation): void
    {
        $conversation->update(['unread_count' => 0]);
    }
}
