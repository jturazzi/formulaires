<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    public function view(User $user, Form $form): bool
    {
        return $user->isAdmin() || $form->user_id === $user->id || $form->shares()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Form $form): bool
    {
        return $this->view($user, $form);
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->isAdmin() || $form->user_id === $user->id;
    }

    /**
     * Add/remove collaborators — anyone with access to the form (owner, admin,
     * or an existing collaborator) can bring in more people.
     */
    public function manageShares(User $user, Form $form): bool
    {
        return $this->view($user, $form);
    }
}
