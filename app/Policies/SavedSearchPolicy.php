<?php

namespace App\Policies;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SavedSearchPolicy
{
    /**
     * Someone else's saved search is reported as not found rather than
     * forbidden, so ids don't reveal which searches exist.
     */
    public function delete(User $user, SavedSearch $savedSearch): Response
    {
        return $savedSearch->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
