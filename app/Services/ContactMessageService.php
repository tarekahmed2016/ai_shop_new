<?php

namespace App\Services;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ContactMessageService
{
    /**
     * @var list<string>
     */
    private const ACTIVITY_FIELDS = ['is_read'];

    public function __construct(public ActivityLogService $activityLogService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createPublicMessage(array $data): ContactMessage
    {
        return ContactMessage::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'is_read' => false,
            'read_at' => null,
        ]);
    }

    public function getPaginatedContactMessages(
        string $search = '',
        string $statusFilter = 'all',
        int $perPage = 15
    ): LengthAwarePaginator {
        return ContactMessage::query()
            ->when($statusFilter === 'read', fn ($q) => $q->where('is_read', true))
            ->when($statusFilter === 'unread', fn ($q) => $q->where('is_read', false))
            ->when($search, fn ($q) => $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function markAsRead(ContactMessage $contactMessage, ?User $actor = null): ContactMessage
    {
        return DB::transaction(function () use ($contactMessage, $actor) {
            $originalValues = $contactMessage->only(self::ACTIVITY_FIELDS);

            $contactMessage->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

            $this->activityLogService->recordChanges(
                subject: $contactMessage,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($contactMessage),
                metadata: ['action' => 'read'],
                actor: $actor,
            );

            return $contactMessage->fresh();
        });
    }

    public function markAsUnread(ContactMessage $contactMessage, ?User $actor = null): ContactMessage
    {
        return DB::transaction(function () use ($contactMessage, $actor) {
            $originalValues = $contactMessage->only(self::ACTIVITY_FIELDS);

            $contactMessage->update([
                'is_read' => false,
                'read_at' => null,
            ]);

            $this->activityLogService->recordChanges(
                subject: $contactMessage,
                originalValues: $originalValues,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($contactMessage),
                metadata: ['action' => 'unread'],
                actor: $actor,
            );

            return $contactMessage->fresh();
        });
    }

    public function delete(ContactMessage $contactMessage, ?User $actor = null): void
    {
        DB::transaction(function () use ($contactMessage, $actor) {
            $this->activityLogService->recordDeleted(
                subject: $contactMessage,
                allowedFields: self::ACTIVITY_FIELDS,
                subjectLabel: $this->subjectLabel($contactMessage),
                metadata: ['action' => 'delete'],
                actor: $actor,
            );

            $contactMessage->delete();
        });
    }

    private function subjectLabel(ContactMessage $contactMessage): string
    {
        return 'Contact Message: '.($contactMessage->name ?: 'Visitor');
    }
}
