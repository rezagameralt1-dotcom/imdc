<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(?User $user): bool
    {
        return (bool) $user?->is_admin;
    }

    public function view(User $user, Post $post): bool
    {
        return (bool) $user->is_admin || $user->id === $post->author_id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_admin; // در این نسخه، فقط ادمین تولید کند
    }

    public function update(User $user, Post $post): bool
    {
        return (bool) $user->is_admin || $user->id === $post->author_id;
    }

    public function delete(User $user, Post $post): bool
    {
        return (bool) $user->is_admin || $user->id === $post->author_id;
    }
}

