<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('loja.{lojaId}.chat', function ($user, $lojaId) {
    return false;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) return false;
    return (bool) ($user->empresa?->id) && (int) $user->empresa->id === (int) $conversation->empresa_id;
});

Broadcast::channel('empresa.{empresaId}.chat', function ($user, $empresaId) {
    return (bool) ($user->empresa?->id) && (int) $user->empresa->id === (int) $empresaId;
});
