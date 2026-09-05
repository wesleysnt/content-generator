<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ContentRequest;
use App\Models\User;

class ContentRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentRequest $request): bool
    {
        return $user->isAdmin() || $user->id === $request->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContentRequest $request): bool
    {
        return $user->isAdmin() || $user->id === $request->user_id;
    }

    public function delete(User $user, ContentRequest $request): bool
    {
        return $user->isAdmin();
    }

    public function generate(User $user, ContentRequest $request): bool
    {
        return $user->isAdmin() || $user->id === $request->user_id;
    }
}
