<?php

namespace App\Policies;

use App\Models\BankConnection;
use App\Models\User;

class BankConnectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->id > 0;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BankConnection $bankConnection): bool
    {
        return $bankConnection->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->id > 0;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BankConnection $bankConnection): bool
    {
        return $bankConnection->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BankConnection $bankConnection): bool
    {
        return $bankConnection->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BankConnection $bankConnection): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BankConnection $bankConnection): bool
    {
        return false;
    }
}
