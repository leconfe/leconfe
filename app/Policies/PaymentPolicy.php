<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment)
    {
        if ($payment->user?->is($user) || $user->can('Payment:view')) {
            return true;
        }
    }

    public function viewAny(User $user)
    {
        if ($user->can('Payment:viewAny')) {
            return true;
        }
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        if ($user->can('Payment:create')) {
            return true;
        }
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Payment $payment)
    {
        if ($user->can('Payment:update')) {
            return true;
        }
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Payment $payment)
    {
        if ($user->can('Payment:delete')) {
            return true;
        }
    }
}
