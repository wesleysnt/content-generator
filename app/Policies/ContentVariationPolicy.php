<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentVariation;
use App\Models\User;

class ContentVariationPolicy
{
    public function view(User $user, ContentVariation $variation): bool
    {
        return $user->isAdmin() || $user->id === $variation->request->user_id;
    }

    public function update(User $user, ContentVariation $variation): bool
    {
        return $user->isAdmin() || $user->id === $variation->request->user_id;
    }

    public function lock(User $user, ContentVariation $variation): bool
    {
        return $user->isAdmin() || $user->id === $variation->request->user_id;
    }

    public function regenerate(User $user, ContentVariation $variation): bool
    {
        return ($user->isAdmin() || $user->id === $variation->request->user_id)
            && ! $variation->is_locked;
    }
}
