<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WatchlistItem;

class WatchlistItemPolicy
{
    public function delete(User $user, WatchlistItem $watchlistItem): bool
    {
        return $watchlistItem->user_id === $user->id;
    }
}
