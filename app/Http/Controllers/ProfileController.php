<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\MerchantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        public MerchantContext $merchantContext,
    ) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/EditPage', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'status_formatted' => $user->status_formatted,
            ],
            'merchantContext' => $this->merchantContext->toArray(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email', 'phone']));

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('profile.edit')->with('success', 'تم تحديث الملف الشخصي بنجاح');
    }
}
