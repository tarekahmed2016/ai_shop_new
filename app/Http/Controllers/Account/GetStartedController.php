<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\UserCapabilities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GetStartedController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if ($redirect = admin_dashboard_redirect()) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user !== null, 403);

        return Inertia::render('Account/GetStartedPage', [
            'capabilities' => UserCapabilities::for($user),
        ]);
    }
}
