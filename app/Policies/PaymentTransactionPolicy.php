<?php

namespace App\Policies;

use App\Models\PaymentTransaction;
use App\Models\User;
use App\Support\AdminAccess;

class PaymentTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return AdminAccess::allows($user, 'payments.view');
    }

    public function view(User $user, PaymentTransaction $paymentTransaction): bool
    {
        return AdminAccess::allows($user, 'payments.view');
    }
}
