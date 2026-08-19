<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactMessageRequest;
use App\Services\ContactMessageService;
use Illuminate\Http\RedirectResponse;

class PublicContactController extends Controller
{
    public function __construct(public ContactMessageService $contactMessageService) {}

    public function store(StoreContactMessageRequest $request): RedirectResponse
    {
        $this->contactMessageService->createPublicMessage(
            data: $request->safe()->only(['name', 'email', 'phone', 'subject', 'message']),
        );

        return redirect()->back()->with('success', 'contact_message_sent');
    }
}
