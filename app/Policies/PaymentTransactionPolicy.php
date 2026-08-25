<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;

class PaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return $user->hasRole('admin');
    }
}
