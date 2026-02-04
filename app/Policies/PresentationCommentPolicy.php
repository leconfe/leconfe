<?php

namespace App\Policies;

use App\Models\PresentationComment;
use App\Models\User;

class PresentationCommentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
		return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PresentationComment $comment)
    {
        if ($user->can('Comment:update')) {
            return true;
        }
    }
}
