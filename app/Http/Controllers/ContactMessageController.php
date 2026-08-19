<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\ContactMessageService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactMessageController extends Controller
{
    public function __construct(public ContactMessageService $contactMessageService) {}

    public function index(Request $request): Response
    {
        $search = (string) $request->input('search', '');
        $statusFilter = in_array($request->input('status'), ['all', 'read', 'unread']) ? $request->input('status') : 'all';

        $contactMessages = $this->contactMessageService->getPaginatedContactMessages(
            search: $search,
            statusFilter: $statusFilter,
        );

        return Inertia::render('ContactMessages/ContactMessagesPage', [
            'contactMessages' => $contactMessages,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function markAsRead(ContactMessage $contactMessage)
    {
        $this->contactMessageService->markAsRead(contactMessage: $contactMessage);

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function markAsUnread(ContactMessage $contactMessage)
    {
        $this->contactMessageService->markAsUnread(contactMessage: $contactMessage);

        return redirect()->back()->with('success', 'تم التحديث بنجاح');
    }

    public function destroy(ContactMessage $contactMessage)
    {
        $this->contactMessageService->delete(contactMessage: $contactMessage);

        return redirect()->back()->with('success', 'تم الحذف بنجاح');
    }
}
