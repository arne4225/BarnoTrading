<?php

namespace App\Policies;

use App\Models\PriceAlert;
use App\Models\User;

class PriceAlertPolicy
{
    public function update(User $user, PriceAlert $priceAlert): bool
    {
        return $priceAlert->user_id === $user->id;
    }

    public function delete(User $user, PriceAlert $priceAlert): bool
    {
        return $priceAlert->user_id === $user->id;
    }
}
