<?php

namespace App\Policies;

use App\Models\Trade;
use App\Models\User;

class TradePolicy
{
    public function update(User $user, Trade $trade): bool
    {
        return $trade->user_id === $user->id;
    }
}
