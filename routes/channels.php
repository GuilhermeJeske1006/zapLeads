<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('loja.{lojaId}.chat', function ($user, $lojaId) {
    return (bool) $user->lojas()->where('id', $lojaId)->exists();
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = \App\Models\Conversation::find($conversationId);
    if (!$conversation) return false;
    return (bool) $user->lojas()->where('id', $conversation->loja_id)->exists();
});
