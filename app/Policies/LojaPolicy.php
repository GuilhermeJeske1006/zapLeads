<?php

namespace App\Policies;

use App\Models\Loja;
use App\Models\User;

class LojaPolicy
{
    public function update(User $user, Loja $loja): bool
    {
        return $user->id === $loja->user_id;
    }

    public function delete(User $user, Loja $loja): bool
    {
        return $user->id === $loja->user_id;
    }
}
