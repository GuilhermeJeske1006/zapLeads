<?php

namespace App\Repositories;

use App\Models\Conversation;
use App\Models\Empresa;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Collection;

class ConversationRepository
{
    public function getForEmpresa(Empresa $empresa, string $search = ''): Collection
    {
        return Conversation::query()
            ->where('empresa_id', $empresa->id)
            ->where('status', 'active')
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('nome_contato', 'like', "%{$search}%")
                  ->orWhere('telefone', 'like', "%{$search}%")
                  ->orWhere('telefone_e164', 'like', "%{$search}%")
                  ->orWhere('last_message', 'like', "%{$search}%");
            }))
            ->orderByDesc('last_message_at')
            ->with('lead')
            ->get();
    }

    public function findOrCreateByPhone(Empresa $empresa, string $phone, ?string $nome = null): Conversation
    {
        $e164 = Phone::canonical($phone, $empresa->country);

        return Conversation::firstOrCreate(
            $e164 ? ['empresa_id' => $empresa->id, 'telefone_e164' => $e164] : ['empresa_id' => $empresa->id, 'telefone' => $phone],
            ['telefone' => $phone, 'nome_contato' => $nome, 'status' => 'active']
        );
    }

    public function markAsRead(Conversation $conversation): void
    {
        $conversation->update(['unread_count' => 0]);
    }
}
